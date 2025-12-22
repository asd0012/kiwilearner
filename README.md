# KiwiLearner


## Deployment

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
