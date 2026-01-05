import Templates from 'core/templates';
import ModalFactory from 'core/modal_factory';

export const init = (data) => {
    // Don’t explode if data is missing.
    if (!data || !Array.isArray(data.items)) {
        return;
    }

    Templates.render('block_quizgenerate/summary_modal', data)
        .then((html) => ModalFactory.create({
            type: ModalFactory.types.DEFAULT,
            title: 'Quiz Summary',
            body: html,
            large: true
        }))
        .then((modal) => modal.show())
        .catch((err) => {
            // eslint-disable-next-line no-console
            console.error('[block_quizgenerate] summary modal failed:', err);
        });
};

