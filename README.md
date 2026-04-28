![KiwiLearner Logo](asset/Logo.png)

# KiwiLearner

**Moodle-based learning support plugins for daily learning engagement, XP tracking, goals, streaks, reminders, and context-aware student assistance.**

KiwiLearner was developed as a University of Canterbury capstone group project. The project explores how small, visible learning actions can help students stay engaged with course material through daily quizzes, progress feedback, gamified motivation, and lightweight support tools inside Moodle.

> Status: capstone prototype / development project. Some features are complete, while others remain experimental or planned future work.

---

## Table of Contents

* [Overview](#overview)
* [Key Features](#key-features)
* [My Contributions](#my-contributions)
* [System Components](#system-components)
* [Technology Stack](#technology-stack)
* [Repository Structure](#repository-structure)
* [Getting Started](#getting-started)
* [Running the Project](#running-the-project)
* [Testing](#testing)
* [Demo](#demo)
* [Limitations and Future Work](#limitations-and-future-work)
* [Acknowledgements](#acknowledgements)

---

## Overview

KiwiLearner is a set of custom Moodle plugins designed to support student engagement through a daily learning loop:

1. Students complete daily quiz or learning activities.
2. The system awards XP for participation and correct answers.
3. Daily progress is shown through goals, summaries, and streaks.
4. Reminder emails encourage students to return before the end of the day.
5. Context-aware support tools help students navigate learning tasks and course information.

The project focuses on practical Moodle plugin development, database-backed progress tracking, student-facing UI, email workflows, and integration across multiple plugin components.

---

## Key Features

### XP, Goals, and Streaks

* Awards XP for selected learning activities.
* Tracks daily XP progress per course.
* Supports daily learning goals.
* Tracks current and best streaks.
* Uses an XP event ledger to reduce duplicate XP counting.

### Daily Quiz Block

* Provides a daily quiz workflow inside Moodle.
* Supports question-bank-based daily practice.
* Allows students to reattempt incorrect answers.
* Shows daily quiz summaries and progress feedback.
* Supports email summary functionality.

### Reminder Emails

* Sends reminder-style nudges when students have not completed their daily goal.
* Designed around scheduled reminder windows.
* Helps students return to the daily quiz or learning task before the day ends.

### Context-Aware Learning Support

* Includes a chatbot-style support component.
* Surfaces course-related information such as upcoming deadlines and recent updates.
* Explores context-aware assistance depending on where the student is inside Moodle.

### Interactive Video / H5P Exploration

* Explores H5P / interactive video learning activities.
* Investigates awarding XP from in-video questions or related learning interactions.
* Intended to connect video-based engagement with the same XP and daily progress loop.

---

## My Contributions

This was a group capstone project. My main contributions focused on the XP, daily goal, streak, reminder, and Daily Quiz areas.

I worked on:

* XP engine and database-backed XP event tracking.
* Daily goal progress and streak logic.
* Daily Quiz workflows and summary pages.
* Email summary functionality.
* Reminder email logic and related testing.
* Moodle plugin integration across the daily learning loop.
* Preventing duplicate XP counting for repeated submissions.
* Debugging, database checks, and PHPUnit-based testing for core behaviours.

The project also involved team communication, sprint planning, task breakdown, integration work, and coordination across multiple Moodle plugins.

---

## System Components

KiwiLearner is organised around several Moodle plugin components:

| Component                     | Purpose                                                                     |
| ----------------------------- | --------------------------------------------------------------------------- |
| `local/kiwilearner`           | Core XP, daily goal, streak, and shared logic.                              |
| `block/kiwilearner_dailyquiz` | Daily quiz workflow, reattempt flow, summaries, and email summary features. |
| `block/kiwilearner_chatbot`   | Context-aware course assistance and student support UI.                     |
| `mod/kiwivideo`               | Experimental video / H5P-related learning activity integration.             |

---

## Technology Stack

* **Backend / Moodle:** PHP, Moodle plugin APIs
* **Frontend:** JavaScript, Mustache templates, CSS / SCSS
* **Database:** Moodle database APIs, SQL, MariaDB / MySQL-style development environment
* **Development Environment:** Docker, Moodle Docker
* **Testing:** PHPUnit, manual Moodle workflow testing
* **Version Control:** Git, GitHub / GitLab-style workflow

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

Before running the project locally, install:

* Docker
* Git
* WSL2 if using Windows
* A terminal environment suitable for running shell scripts

For Windows users, WSL2 is recommended so the project can be run in a Linux-like development environment.

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

Default local development credentials may be configured by the setup script. Check the setup output and project configuration files if the login details differ.

To stop the environment:

```bash
make down
```

---

## Testing

The project includes PHPUnit-based testing for selected plugin behaviours.

Example areas tested include:

* XP event uniqueness and duplicate-prevention behaviour.
* XP synchronisation from Daily Quiz activity.
* Daily XP summary updates.
* Goal and streak update logic.

Typical Moodle PHPUnit commands depend on the local Moodle Docker setup and Moodle configuration. If PHPUnit is already configured, tests can be run from the Moodle root using the standard Moodle PHPUnit workflow.

---

## Demo

Demo videos and screenshots can be added here.

Suggested demo items:

* XP system, daily summary, and email summary workflow.
* Question-level XP customisation and H5P-related learning activity integration.
* Daily goal and streak progress UI.

If demo files are large, they should be uploaded through GitHub Releases or an external video link rather than committed directly into the repository.

---

## Limitations and Future Work

KiwiLearner is a capstone prototype, not a production-ready Moodle plugin package. Current limitations include:

* Some features were implemented as prototypes or development-only workflows.
* Full LMS deployment and production hardening were outside the project scope.
* Some AI / video-alignment ideas were explored but not fully completed.
* Further usability testing with real course students would be needed.
* More complete teacher dashboards, analytics, and behaviour logging could be added in future work.

Possible future improvements:

* Cleaner plugin packaging for Moodle administrators.
* More robust reminder scheduling and opt-out controls.
* Expanded quiz question types and auto-generated practice activities.
* Better dashboards for students and teachers.
* Deeper integration between video learning activities, quiz attempts, XP, and feedback.

---

## Acknowledgements

This project was developed as part of the University of Canterbury COSC680 capstone project.

KiwiLearner was a group project. My work focused mainly on the XP engine, daily goals, streak logic, reminders, Daily Quiz workflows, summaries, email summary features, testing, and Moodle plugin integration.

2. Kiwi-backups folder will be created for your config files

3. The timestamp will be the end of your config files in kiwi-backups folder.
    e.g. moodle-2025-12-07_230101.sql
4. Run **make restore STAMP=xxxx-xx-xx_xxxxxx** to restore your config.
    e.g make restore STAMP=2025-12-07_230101
