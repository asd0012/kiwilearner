![KiwiLearner Logo](asset/Logo.png)

# KiwiLearner

**A Moodle-based learning engagement prototype that turns daily learning actions into visible progress through XP, goals, streaks, reminders, daily quizzes, summaries, and context-aware student support.**

KiwiLearner was developed as a University of Canterbury COSC680 capstone group project. The project explores how Moodle can provide more timely feedback and motivation for students by connecting daily practice activities with progress tracking, gamified feedback, and lightweight reminders.

> **Status:** Capstone prototype / development project. The core daily learning loop was implemented and demonstrated, while some advanced analytics, video-alignment, and dashboard features remain future work.

---

## Table of Contents

* [Overview](#overview)
* [Problem and Motivation](#problem-and-motivation)
* [Core Learning Loop](#core-learning-loop)
* [Key Features](#key-features)
* [My Contributions](#my-contributions)
* [System Components](#system-components)
* [Technology Stack](#technology-stack)
* [Engineering Highlights](#engineering-highlights)
* [Testing and Validation](#testing-and-validation)
* [Exploratory Reminder Simulation](#exploratory-reminder-simulation)
* [Repository Structure](#repository-structure)
* [Getting Started](#getting-started)
* [Running the Project](#running-the-project)
* [Demo](#demo)
* [Limitations and Future Work](#limitations-and-future-work)
* [Acknowledgements](#acknowledgements)

---

## Overview

KiwiLearner is a suite of custom Moodle plugins designed to improve student engagement by making daily learning progress easier to see, repeat, and act on.

The project focuses on three engagement mechanisms:

* **Prompt** — reminder emails and chatbot-style support help students return to learning tasks.
* **Practice** — daily quizzes and summaries provide low-friction retrieval practice.
* **Motivate** — XP, daily goals, streaks, and progress summaries make small learning actions visible.

Rather than replacing Moodle, KiwiLearner extends Moodle through custom local, block, and activity plugins.

---

## Problem and Motivation

Learning Management Systems such as Moodle provide access to course resources, quizzes, and recorded lectures, but students can still struggle with day-to-day engagement. Standard LMS interactions often show whether a task was completed, but provide limited immediate feedback about progress, consistency, or how learning actions build over time.

KiwiLearner addresses this by creating a daily learning loop where students complete small activities, receive XP, see goal and streak feedback, review a summary, and receive reminders when they have not yet met their target.

---

## Core Learning Loop

```text
Daily Quiz / Moodle Quiz / H5P activity
        ↓
XP event recording
        ↓
Daily XP summary
        ↓
Goal status + streak update
        ↓
Summary page / email summary / reminder email
```

The main design goal was to keep progress visible and consistent across different Moodle learning activities.

---

## Key Features

### XP, Daily Goals, and Streaks

* Records XP from supported learning activities.
* Tracks daily XP totals per student and course.
* Supports student daily XP goals.
* Displays goal status such as Achieved, Missed, or Unknown.
* Maintains streak progress when students meet their daily target.
* Supports milestone-style streak messages.

### Daily Quiz Block

* Generates daily quiz activities from Moodle question banks.
* Supports student submission and progress feedback.
* Allows reattempting incorrect questions.
* Displays daily quiz summaries.
* Provides an email summary option for students.

### Reminder Emails

* Uses scheduled-task-style reminder logic.
* Checks whether students have met their daily goal.
* Sends reminder emails to students who have not completed their target.
* Includes deduplication / rate-limit logic to reduce repeated reminder spam.

### Context-Aware Chatbot Block

* Provides a student-facing chat interface inside Moodle.
* Adapts basic responses based on Moodle page context.
* Can surface course-related support such as deadlines and recent updates.
* Explores PDF-based summary evaluation and tutor-forwarding workflows.

### Interactive Video / H5P Integration

* Integrates with Moodle H5P interactive video content.
* Supports XP-related handling for in-video question interactions.
* Uses H5P as the interactive video provider rather than modifying H5P source code.
* Connects interactive video learning activity into the same daily XP loop.

---

## My Contributions

This was a group capstone project. My main work focused on the XP, daily goal, streak, reminder, and Daily Quiz parts of the system.

I contributed to:

* XP engine and XP event tracking.
* Daily goal setup and persistence.
* Goal status calculation using daily XP totals.
* Streak tracking and streak display logic.
* Daily Quiz workflows and summary pages.
* Reattempt flow for incorrect quiz answers.
* Email summary functionality.
* Reminder email logic and scheduling behaviour.
* Duplicate XP prevention for repeated submissions or refreshes.
* Database-backed debugging and verification.
* PHPUnit-based checks for selected high-risk behaviours.
* A simple Makefile helper for creating development database backups before risky schema changes or integration testing.

The project also required regular team communication, sprint planning, task breakdown, merge/review discussions, troubleshooting, and integration work across multiple Moodle plugins.

---

## System Components

| Component                     | Type                   | Purpose                                                                      |
| ----------------------------- | ---------------------- | ---------------------------------------------------------------------------- |
| `local/kiwilearner`           | Moodle local plugin    | Core XP framework, daily goals, streaks, shared logic, and event handling.   |
| `block/kiwilearner_dailyquiz` | Moodle block plugin    | Daily quiz workflow, reattempt logic, summaries, and email summary features. |
| `block/kiwilearner_chatbot`   | Moodle block plugin    | Context-aware student support and course assistance UI.                      |
| `mod/kiwivideo`               | Moodle activity plugin | H5P / interactive video-related learning activity integration.               |

---

## Technology Stack

* **Platform:** Moodle
* **Backend:** PHP, Moodle plugin APIs
* **Frontend:** JavaScript, Mustache templates, CSS / SCSS
* **Database:** Moodle database APIs, SQL, MariaDB / MySQL-style development environment
* **Environment:** Docker, Moodle Docker, Linux / WSL2-friendly workflow
* **Testing:** Manual scenario testing, PHPUnit, database verification, Mailpit email testing
* **Version Control:** Git, GitHub / GitLab-style feature-branch workflow

---

## Engineering Highlights

### Idempotent XP Pipeline

A major engineering concern was preventing duplicate XP when students refreshed pages, reattempted questions, or when sync logic was run more than once. The system uses stable daily identifiers and uniqueness-style checks so the same logical daily quiz event does not inflate XP repeatedly.

### Daily Time Boundary Handling

Daily features depend heavily on the definition of “today”. The project standardised daily identifiers such as day keys and day-start timestamps so summaries, reminders, and streak updates refer to the same daily bucket.

### Single Source of Truth for Daily XP

Earlier iterations produced inconsistent XP displays between the course block, summary page, and email summary. This was fixed by consolidating daily XP totals around a shared summary source so the UI and emails report consistent progress.

### UI + Database Evidence Debugging

Many bugs were diagnosed by checking both what the user saw in Moodle and what was stored in the database. This helped separate UI rendering issues from backend aggregation, persistence, or scheduled-task problems.

### Database Backup Helper

Because Moodle plugin development depends on persistent database state, I added a simple Makefile helper for creating development backups. This was mainly used as a safety net during local development, so the team could preserve a known database state before risky schema changes, resets, or integration testing.

This was not a full production-grade backup and recovery system; it was a practical developer convenience for reducing the risk of losing local test data.

---

## Testing and Validation

Testing was integrated throughout development instead of being left until the end. The main approach was acceptance-style manual testing through realistic Moodle user flows.

Example tested flows:

* Student sets a daily goal.
* Student generates and submits a daily quiz.
* Summary page shows correct / incorrect answers.
* Incorrect questions can be reattempted.
* XP is recorded and daily totals update correctly.
* Goal status and streak values display correctly.
* Summary email is sent and contains the correct daily progress.
* Reminder email logic identifies students who have not met their target.

Additional checks were added for high-risk behaviours:

* Duplicate XP prevention.
* Streak increment / reset / idempotency.
* Daily XP sync correctness.
* Daily rollover and day-key consistency.
* Email delivery through local mail testing.

---

## Exploratory Reminder Simulation

As part of the capstone evaluation, I ran a lightweight simulation to explore the research question:

> Do reminder emails increase completion of a daily quiz task in a small-scale trial?

This was not a production Moodle deployment or a fully controlled user study. Instead, I simulated the daily quiz and goal experience with Google Forms. Participants selected a daily goal / question count and completed short quiz tasks across several days. Reminder emails were sent manually to approximate the planned reminder workflow.

Simulation design:

* 4 participants
* 6 days
* Days 1–3: no manual reminder emails
* Days 4–6: manual reminder emails
* Completion counted when a participant submitted at least one quiz response for that day

Summary result:

| Condition              | Opportunities | Completed | Completion Rate |
| ---------------------- | ------------: | --------: | --------------: |
| No reminders           |            12 |         7 |           58.3% |
| Manual reminder emails |            12 |         8 |           66.7% |

The reminder period showed a small increase in completion rate, but this should only be treated as an exploratory simulation result. It does not prove that reminder emails caused higher completion. The result is limited by the small sample size, manual reminder process, fixed condition order, possible weekday/weekend effects, and likely participant awareness of being in a study.

---

## Repository Structure

```text
kiwilearner/
├── moodle/                 # Moodle source tree with KiwiLearner plugins
├── moodle-docker/          # Docker-based Moodle development environment
├── data/moodle-xml/        # Moodle XML / data-related resources
├── documentations/         # Project documentation and notes
├── asset/                  # Images and project assets
├── setup.sh                # Local setup helper script
├── Makefile                # Docker / development helper commands
└── README.md
```

The repository includes both the Moodle development environment and the KiwiLearner plugin code so the system can be run locally for development and demonstration.

---

## Getting Started

### Prerequisites

Install:

* Docker
* Git
* WSL2 if using Windows
* A terminal environment capable of running shell scripts and Makefile commands

For Windows users, WSL2 is recommended so the project can run in a Linux-like environment.

---

## Running the Project

Clone the repository:

```bash
git clone https://github.com/asd0012/kiwilearner.git
cd kiwilearner
```

Run the setup script:

```bash
bash setup.sh
```

Start the Moodle Docker environment:

```bash
make up
```

The local Moodle site should then be available at:

```text
http://localhost:8000
```

To stop the environment without removing containers or volumes:

```bash
make stop
```

For destructive cleanup, check the `clean` and `nuke` targets in the Makefile before running them, because they may remove containers, volumes, or local development data.

---

## Demo

Demo videos and screenshots can be added here.

Suggested demo items:

* XP system, daily summary, and email summary workflow.
* Question-level XP customisation and H5P-related learning activity integration.
* Daily goal, goal status, and streak progress UI.
* Reminder email workflow.


- [KiwiLearner Demo – XP, Daily Quiz, Summary and Email Features](https://www.youtube.com/watch?v=1hHtOZQtr3A)
- [KiwiLearner Demo – Question XP Customisation and H5P Integration](https://www.youtube.com/watch?v=yhONCg86u6M)
- [KiwiLearner Demo – Context-Aware Chatbot / Learning Support](https://www.youtube.com/watch?v=_YBsZnZs68E)

These videos demonstrate selected prototype features from the KiwiLearner capstone project, including XP tracking, daily quiz workflows, summaries, email features, H5P-related integration, and context-aware Moodle support.
---

## Limitations and Future Work

KiwiLearner is a capstone prototype, not a production-ready Moodle plugin package.

Known limitations:

* Some workflows were built for demonstration and controlled testing rather than production deployment.
* Goal reset behaviour still needs stronger integrity controls to prevent students from lowering the goal late in the day to preserve a streak.
* Advanced segment-level video tagging and precise jump-to-segment remediation were not fully implemented.
* Full behaviour logging for video interactions was deferred.
* Instructor dashboards and analytics export were not delivered in the MVP.
* Some chatbot and AI-related features remain experimental or limited in scope.

Future improvements:

* Cleaner plugin packaging for Moodle administrators.
* Stronger goal-reset rules and audit logging.
* More robust reminder preferences, rate limits, and opt-out controls.
* Richer daily quiz generation using difficulty tags or content links.
* Better dashboards for students and teachers.
* Deeper integration between quiz attempts, video resources, XP, summaries, and targeted feedback.
* Broader automated test coverage for Moodle plugin behaviours.

---

## Acknowledgements

This project was developed as part of the University of Canterbury COSC680 capstone project.

KiwiLearner was a group project by Tsung-Te Huang, Yue Pan, and Najiya Pattanath Mullassery. My work focused mainly on the XP engine, daily goals, streak logic, reminders, Daily Quiz workflows, summaries, email summary features, testing, and Moodle plugin integration.
