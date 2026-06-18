# Project Management Center Backend


## Development


 Development is run using **Laravel Sail**. To start, run `sail up -d`. There is no specific database container provided for this development environment. It is assumed that developers already have their own database server, running either on the host machine or on another server. Please configure the connection string in your `.env` file before running the application for development.

## Staging



To deploy the app to the staging environment, simply run ```./run.dev.sh``` on the target server. The environment file (.env) used in staging is located at ```/docker/staging/.env.dev```. Update the database credentials, ports, and configuration settings to suit the staging requirements.

To stop and remove the containers, run ```./down.dev.sh.```


