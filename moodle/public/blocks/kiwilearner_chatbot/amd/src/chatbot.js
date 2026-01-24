define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    const SEL = {
        root: '.kiwi-chatbot',
        launch: '.kiwi-chatbot-launch',
        win: '.kiwi-chatbot-window',
        close: '.kiwi-chatbot-close',
        messages: '[data-region="kiwi-chatbot-messages"]',
        input: '.kiwi-chatbot-input',
        send: '.kiwi-chatbot-send'
    };

    let surveyState = null;   // global for this chat session
    let surveyData = {
        cmid: null,
        read_status: '',
        completion: '',
        about_text: '',
        takeaways_text: ''
    };
        //helper functions for resource context
        /**
         * Check whether current page is a Moodle resource view page.
         *
         * @returns {boolean}
         */
        function isResourceViewPage() {
                return window.location.pathname.indexOf('/mod/resource/view.php') !== -1;
        }

        /**
        * Get a query string parameter by name.
        *
        * @param {string} name
        * @returns {string|null}
        */
        function getQueryParam(name) {
            const params = new URLSearchParams(window.location.search);
            return params.get(name);
        }
        //backend feature for resource context
        /**
         * If the user is on a PDF resource page, add a debug message (Step 1).
         *
         * @param {jQuery} $box Messages container
         * @returns {void}
         */
        function maybeStartPdfSurvey($box) {
            if (!isResourceViewPage()) {
                return;
            }

            const cmid = getQueryParam('id');
            if (!cmid) {
                return;
            }

            //const $messages = $(SELECTORS.messages);

            // Temporary debug message so you can confirm the trigger works.
            //addMsg($box, '🔎 Detected resource page. CMID = <b>' + cmid + '</b>.', 'bot');

            // Next step (after Step 2): uncomment this Ajax call.
            Ajax.call([{
                methodname: 'block_kiwilearner_chatbot_detect_resource_context',
                args: { cmid: parseInt(cmid, 10) }
            }])[0].done(function(resp) {
                if (!(resp && resp.is_pdf)) {
                    return;
                }

                surveyData.cmid = parseInt(cmid, 10); /*else {
                    addMsg($box, 'This resource does not look like a PDF.', 'bot');
                }*/
                Ajax.call([{
                    methodname: 'block_kiwilearner_chatbot_cache_pdf_text',
                    args: { cmid: surveyData.cmid }
                }])[0].done(function(r) {
                    addMsg($box, 'PDF cache: ' + escapeHtml(r.message) + ' (chars=' + r.chars + ')', 'bot');
                }).fail(function(ex) {
                    Notification.exception(ex);
                });
                // ✅ ADD HERE (1): store cmid in a module-level variable for saving later.
                surveyData.cmid = parseInt(cmid, 10);

                // ✅ ADD HERE (2): load existing survey state from DB (resume support).
                Ajax.call([{
                    methodname: 'block_kiwilearner_chatbot_get_pdf_survey',
                    args: {cmid: surveyData.cmid}
                }])[0].done(function(s) {

                    // If we found a saved record, resume.
                    if (s && s.found && s.state) {
                        surveyState = s.state;

                        surveyData.read_status = s.read_status || '';
                        surveyData.completion = s.completion || '';
                        surveyData.about_text = s.about_text || '';
                        surveyData.takeaways_text = s.takeaways_text || '';

                        addMsg($box, '📄 You opened: <b>' + escapeHtml(resp.resource_name) + '</b>. Resuming…', 'bot');
                    } else {
                        // New survey.
                        surveyState = 'ask_read';
                        addMsg($box, '📄 You opened: <b>' + escapeHtml(resp.resource_name) + '</b>.', 'bot');
                    }

                    // ✅ ADD HERE (3): prompt the right next question based on surveyState.
                    if (surveyState === 'ask_read') {
                        addMsg($box, 'Have you read it? (YES / NO / LATER)', 'bot');
                    } else if (surveyState === 'ask_complete') {
                        addMsg($box, 'Did you read it fully? (FULL / PARTIAL)', 'bot');
                    } else if (surveyState === 'ask_about') {
                        addMsg($box, 'In 1–2 sentences, what is this material about?', 'bot');
                    } else if (surveyState === 'ask_takeaways') {
                        addMsg($box, 'Now list 2–3 key takeaways you understood.', 'bot');
                    } else {
                        // Fallback if DB had weird value.
                        surveyState = 'ask_read';
                        addMsg($box, 'Have you read it? (YES / NO / LATER)', 'bot');
                    }

                }).fail(function(ex) {
                    Notification.exception(ex);
                });
            }).fail(function(ex) {
                // If the backend isn't ready yet, don't throw scary errors at the user.
                //addMessage($messages, 'Backend PDF detection is not enabled yet (Step 2).', 'bot');
                Notification.exception(ex);
                //console.warn(ex);
            });

            //trigger cache_pdf_text
            /*
            Ajax.call([{
                methodname: 'block_kiwilearner_chatbot_cache_pdf_text',
                args: { cmid: surveyData.cmid }
            }]);/*[0].done(function(r) {
                console.log('PDF cache:', r);
            }).fail(function(ex) {
                console.warn('PDF cache failed', ex);
            });*/ //unexpected console statement error in grunt amd
        }
    /**
     * Escape HTML to prevent XSS in user-provided text.
     *
     * @param {String} str
     * @returns {String}
     */
    function escapeHtml(str) {
        return String(str)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    /**
     * Append a message to the messages area.
     *
     * @param {jQuery} $box
     * @param {String} html
     * @param {String} who
     * @returns {void}
     */
    function addMsg($box, html, who) {
        const cls = who === 'user' ? 'user' : 'bot';
        const meta = who === 'user' ? 'You' : 'KiwiBot';

        const $m = $(`
            <div class="kiwi-msg ${cls}">
                <div class="meta">${meta}</div>
                <div class="kiwi-bubble">${html}</div>
            </div>
        `);

        $box.append($m);
        $box.scrollTop($box[0].scrollHeight);
    }
    /**
     * Reset the chat to the initial greeting for the current context.
     *
     * @param {jQuery} $box Messages container.
     * @param {number} courseid Course id.
     * @param {number} cmid Course module id.
     * @returns {void}
     */
    function resetConversationToInitial($box, courseid, cmid) {
        $box.empty();

        surveyState = null;
        surveyData = {
            courseid: courseid || 0,
            cmid: cmid || 0
        };
        saveSurveyState();

        Ajax.call([{
            methodname: 'block_kiwilearner_chatbot_get_context_cards',
            args: { courseid: surveyData.courseid, cmid: surveyData.cmid }
        }])[0].done(function(res) {

            if (res && res.greeting) {
                addMsg($box, res.greeting, 'bot');
            } else {
                addMsg($box, 'Kia Ora! How can I help you today?', 'bot');
            }

        }).fail(function(ex) {
            Notification.exception(ex);
            addMsg($box, 'Kia Ora! How can I help you today?', 'bot');
        });
    }


    /**
     * Render greeting + deadlines + updates.
     *
     * @param {jQuery} $box
     * @param {Object} data
     * @returns {void}
     */
    function renderCards($box, data) {
        addMsg($box, data.greeting, 'bot');

        if (data.deadlines && data.deadlines.length) {
            let list = '<b>Deadlines in the next 7 days:</b><ul>';
            data.deadlines.forEach(d => {
                const link = d.url ? `<a href="${d.url}">${escapeHtml(d.name)}</a>` : escapeHtml(d.name);
                list += `<li>${link} — ${escapeHtml(d.time)}</li>`;
            });
            list += '</ul>';
            addMsg($box, list, 'bot');
        } else {
            addMsg($box, '<b>Deadlines:</b> No upcoming deadlines in the next 7 days.', 'bot');
        }

        if (data.updates && data.updates.length) {
            let list = '<b>Course updates (last 7 days):</b><ul>';
            data.updates.forEach(u => {
                list += `<li><a href="${u.url}">${escapeHtml(u.title)}</a> — ${escapeHtml(u.time)}</li>`;
            });
            list += '</ul>';
            addMsg($box, list, 'bot');
        } else {
            addMsg($box, '<b>Course updates:</b> No updates in the last 7 days (or not inside a course).', 'bot');
        }
    }

    /**
     * Fetch greeting/deadlines/updates via AJAX.
     *
     * @param {Number} courseid
     * @param {Number} cmid
     * @param {jQuery} $box
     * @returns {void}
     */
    function fetchContext(courseid, cmid, $box) {
        Ajax.call([{
            methodname: 'block_kiwilearner_chatbot_get_context_cards',
            args: {courseid: courseid || 0, cmid: cmid || 0}
        }])[0]
            .done(function(data) {
                renderCards($box, data);
            })
            .fail(function(ex) {
                Notification.exception(ex);
            });
    }

    //saveSurveyData
    /**
     * Save the current survey state/answers to DB.
     *
     * @returns {void}
     */
    function saveSurveyState() {
        if (!surveyData.cmid) {
            return;
        }

        Ajax.call([{
            methodname: 'block_kiwilearner_chatbot_save_pdf_survey',
            args: {
                cmid: surveyData.cmid,
                state: surveyState || '',
                read_status: surveyData.read_status || '',
                completion: surveyData.completion || '',
                about_text: surveyData.about_text || '',
                takeaways_text: surveyData.takeaways_text || ''
            }
        }])[0].fail(function(ex) {
            Notification.exception(ex);
        });
    }


    /**
     * Initialise the chatbot UI.
     *
     * @param {Object} params
     * @returns {void}
     */
    function init(params) {
        $(SEL.root).each(function() {
            const $root = $(this);
            const $launch = $root.find(SEL.launch);
            const $win = $root.find(SEL.win);
            const $close = $root.find(SEL.close);
            const $box = $root.find(SEL.messages);
            const $input = $root.find(SEL.input);
            const $send = $root.find(SEL.send);

            const courseid = parseInt($win.data('courseid'), 10) || (params && params.courseid ? parseInt(params.courseid, 10) : 0);
            const cmid = parseInt($win.data('cmid'), 10) || (params && params.cmid ? parseInt(params.cmid, 10) : 0);


            $launch.on('click', function() {
                $win.removeClass('d-none');
                $launch.addClass('d-none');

                //callthePdfSurvey function
                maybeStartPdfSurvey($box);

                if ($box.children().length === 0) {
                    fetchContext(courseid, cmid, $box);
                }
            });

            $close.on('click', function() {
                $win.addClass('d-none');
                $launch.removeClass('d-none');
            });

            /**
             * Send user input (placeholder).
             *
             * @returns {void}
             */
            function sendUserText() {
                const rawText = ($input.val() || '').trim();//.toUpperCase();
                if (!rawText) {
                    return;
                }

                //clear text field
                $input.val('');

                //echo message
                addMsg($box, escapeHtml(rawText), 'user');

                // If survey not active, keep current baseline behaviour.
                if (!surveyState) {
                    addMsg($box, 'Thanks! (Baseline stage)', 'bot');
                    return;
                }

                const cmd = rawText.toUpperCase();
                if (surveyState === 'post_feedback_menu') {
                    const choice = rawText.trim().toUpperCase();

                    if (choice === '1') {
                        // Go back to takeaways question
                        surveyState = 'ask_takeaways';
                        saveSurveyState();
                        addMsg($box, 'Sure — please re-read and send your takeaways again.', 'bot');
                        return;
                    }

                    if (choice === '2') {
                        // Whatever your "send to tutor" flow is
                        addMsg(
                            $box,
                            'Okay — I will prepare this to send to your tutor. ' +
                            '(Next: confirm tutor email/details)',
                            'bot'
                        );                        // set another state if needed:
                        // surveyState = 'send_to_tutor';
                        // saveSurveyState();
                        return;
                    }

                    if (choice === '3' || choice === 'STOP' || choice === 'RESET') {
                        // Clear and show initial greeting/cards again
                        resetConversationToInitial($box, surveyData.courseid, surveyData.cmid);
                        return;
                    }

                    addMsg($box, 'Please choose 1, 2, or 3.', 'bot');
                    return;
                }

                if (surveyState === 'ask_read') {
                    if (cmd === 'YES') {
                        surveyData.read_status = 'YES';
                        surveyState = 'ask_complete';
                        saveSurveyState();
                        addMsg($box, 'Great! Did you read it fully? (FULL / PARTIAL)', 'bot');
                    } else if (cmd === 'NO') {
                        surveyData.read_status = 'NO';
                        surveyState = 'ask_read';
                        saveSurveyState();
                        addMsg($box, 'No worries 🙂 Please read it and come back, then reply YES when ready.', 'bot');
                    } else if (cmd === 'LATER') {
                        surveyData.read_status = 'LATER';
                        surveyState = 'ask_read';
                        saveSurveyState();
                        addMsg($box, 'Sure! Come back anytime and reply YES when you are ready.', 'bot');
                    } else {
                        addMsg($box, 'Please reply YES, NO, or LATER.', 'bot');
                    }
                    return;
                }


                if (surveyState === 'ask_complete') {
                    if (cmd === 'FULL' || cmd === 'PARTIAL') {
                        surveyData.completion = cmd;
                        surveyState = 'ask_about';
                        saveSurveyState();
                        addMsg($box, 'Nice 👍 In 1–2 sentences, what is this material about?', 'bot');
                    } else {
                        addMsg($box, 'Please reply FULL or PARTIAL.', 'bot');
                    }
                    return;
                }


                if (surveyState === 'ask_about') {
                    surveyData.about_text = rawText;
                    surveyState = 'ask_takeaways';
                    saveSurveyState();
                    addMsg($box, 'Good! Now list 2–3 key takeaways you understood.', 'bot');
                    return;
                }


                if (surveyState === 'ask_takeaways') {
                    //Echo student's takeaways to chat immediately.
                    //addMsg($box, escapeHtml(rawText), 'user');

                    surveyData.takeaways_text = rawText;
                    surveyState = 'done';
                    saveSurveyState();

                    addMsg($box, 'Thanks! I’m now checking your takeaways against the PDF…', 'bot');

                    Ajax.call([{
                        methodname: 'block_kiwilearner_chatbot_evaluate_pdf_takeaways',
                        args: { cmid: surveyData.cmid }
                    }])[0].done(function(r) {

                        if (r && r.ok) {
                            const fb = (r.feedback || '').trim();

                            // ✅ 2) If feedback is empty, show a helpful message instead of blank/{}.
                            addMsg(
                                $box,
                                '<b>AI Feedback:</b><br>' +
                                    escapeHtml(fb !== '' ? fb : 'No feedback returned (empty AI response).'),
                                'bot'
                            );


                            // ✅ 3) Add the 3rd option.
                            addMsg(
                                $box,
                                'What would you like to do next?<br>' +
                                '(1) REREAD and RESUBMIT<br>' +
                                '(2) SEND TO TUTOR<br>' +
                                '(3) STOP and RESET',
                                'bot'
                            );


                            // ✅ 4) Set a follow-up state for handling 1/2/3 next.
                            surveyState = 'post_feedback_menu';
                            saveSurveyState();

                        } else {
                            addMsg(
                                $box,
                                escapeHtml((r && r.feedback) ? r.feedback : 'AI evaluation failed.'),
                                'bot'
                            );

                            // Keep state so user can try again or reset.
                            surveyState = 'post_feedback_menu';
                            saveSurveyState();
                        }

                    }).fail(function(ex) {
                        Notification.exception(ex);

                        // Keep state so user can choose reset after error.
                        surveyState = 'post_feedback_menu';
                        saveSurveyState();
                    });

                    // ❌ Remove this line, it wipes your state too early:
                    // surveyState = null;

                    return;
                }

            }

            $send.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                sendUserText();
            });

            $input.on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    sendUserText();
                }
            });
        });
    }

    return {init: init};
});
