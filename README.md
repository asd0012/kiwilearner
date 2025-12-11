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

####----------------------#####
# setting up
prerequites: docker running... 

#wsl installation
_____________________
cmd: 
wsl --install
set user account (user\password): najiya\kiwilearner_cosc680
to reset user later :
wsl -u <username>
password can be reset inside wsl

why use wsl?

The Visual Studio Code WSL extension lets you use the Windows Subsystem for Linux (WSL) as your full-time development environment right from VS Code. You can develop in a Linux-based environment, use Linux-specific toolchains and utilities, and run and debug your Linux-based applications all from the comfort of Windows.

The extension runs commands and other extensions directly in WSL so you can edit files located in WSL or the mounted Windows filesystem (for example /mnt/c) without worrying about pathing issues, binary compatibility, or other cross-OS challenges. The extension will install VS Code Server inside WSL; the server is independent of any existing VS Code installation in WSL.
_______________________________________

wsl extension in vscodium:

Docker and wsl:
On Windows, Docker Desktop actually uses WSL2 under the hood. So:
- If you’re running Docker, you’re indirectly using WSL already.
- You can run scripts in WSL to prepare/configure your environment, then use Docker to run services.
- For Moodle setups, the clean workflow is:
- WSL → for editing, Git, and running helper scripts (setup.sh).
- Docker → for running Moodle, MySQL/Postgres, and other services in containers.


#clone from gitlab
git clone https://eng-git.canterbury.ac.nz/cosc680-2025/kiwilearner.git
in the pop up window enter the gitlab username and gitlab password 

#set up wsl
from the kiwiliearner obtain moodle and moodle docker into root folder
set the environment variables for 
### SSH key steps... why SSH key credentials ???

####
to open terminal (wsl)
wsl 
cd /mnt/c/Users/LENOVO/kiwilearner/kiwilearner
bash setup.sh

initial run will take some time ()
then run the localhost in firefox browser


