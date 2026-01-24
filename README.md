# KiwiLearner


## Description

![KiwiLearner Logo](asset/Logo.png)

**KiwiLearner** is a Moodle-based learning enhancement project that integrates **an XP-driven motivation system with an intelligent chat interface** to support student engagement, reflection, and consistency in learning.

Built as a set of custom plugins for Moodle, the project focuses on helping students stay aware of their learning tasks, actively engage with course materials, and build positive study habits through lightweight gamification.

---

## 🎯 Project Goals

KiwiLearner aims to:

- Improve student motivation and consistency using XP, goals, and streaks
- Integrate seamlessly into existing Moodle workflows
- Reduce cognitive overload by surfacing relevant information at the right time

---

## ✨ Key Features

### ⭐ XP & Motivation System
- Awards XP for:
  - Quiz participation
  - Correct answers
  - Learning activity engagement
- Supports daily goals per course
- Tracks streaks (5, 10, 20, 30 days)
- Uses an idempotent XP ledger to prevent duplicate rewards

### 🧠 Context-Aware Chat Interface
- Displays upcoming deadlines (next 7 working days)
- Shows recent course updates and announcements
- Adapts responses based on user context:
  - Dashboard
  - Course pages
  - Individual learning resource pages


## Repository Structure

This project is developed and tested using the official **moodlehq/moodle-docker** setup.

The repository includes the Docker-based Moodle development environment **plus** the KiwiLearner plugins inside the same project folder, so developers can clone once and run everything locally with minimal setup.

There are two supported ways to use KiwiLearner:

1. **Developer / Full Stack (Option A):**  
   Clone this repository and run Moodle using `moodle-docker`. This gives a complete local environment (Moodle + database + web server) and includes the plugins already placed in the correct directories.

2. **Plugin-only Install (Option B, recommended for Moodle admins):**  
   Download the plugin packages from the **Releases** page and install them into an existing Moodle site.

---

## Option A — Clone & Run Locally (moodle-docker)

### Steps for Windows:

1. Install WSL2 (Windows Subsystem for Linux) and Docker, open WSL terminal, find a folder for the project.

2. For a seamless development experience, follow the steps [here](https://code.visualstudio.com/docs/remote/wsl), so you can use VS Code to develop the project in WSL with Linux experience.

3. Then follow the Linux/MacOS steps.

4. Once finish, you can also start/stop the Docker Container from Docker GUI

### Steps for Linux/MacOS:

1. Install Docker, find a folder for the project and change the current working directory to that folder (cd command)

2. Clone this KiwiLearner repository:

    `git clone https://eng-git.canterbury.ac.nz/cosc680-2025/kiwilearner.git`

3. Run the setup script

    `bash kiwilearner/setup.sh`
    
    Below is the default settings:

    | Item          | Value                  |
    |---------------|------------------------|
    | Moodle URL    | http://localhost:8000  |
    | Username      | admin                  |
    | Password      | test                   |
    | Admin Email   | admin@example.com      |

    KiwiLearner will automatically run after setup.

4. To run the docker, go to KiwiLearner root directory and run:
    `sudo make up`

5. Use adminer to access the database: http://localhost:[port]/adminer.php

    Below is the default database settings:

    | Item          | Value                  |
    |---------------|------------------------|
    | Server        | localhost              |
    | Username      | moodle                 |
    | Password      | m@0dl3ing              |
    | Database      | moodle                 |

### Steps for backup and restore config

1. Run **make backup** in kiwilearner folder

2. Kiwi-backups folder will be created for your config files

3. The timestamp will be the end of your config files in kiwi-backups folder.
    e.g. moodle-2025-12-07_230101.sql
4. Run **make restore STAMP=xxxx-xx-xx_xxxxxx** to restore your config.
    e.g make restore STAMP=2025-12-07_230101
