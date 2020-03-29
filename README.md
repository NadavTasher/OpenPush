# OpenPush

OpenPush is a simple platform that I implemented in PHP and JavaScript in order to centralize my push notifications support.

## Installation
### Method 1: Using the Docker Hub repository
Install [docker](https://www.docker.com/) on your machine.

Run the following command:
```bash
docker run -p 80:80 --name openpush --restart unless-stopped -d nadavtasher/openpush:latest
```
### Method 2: Building a docker image from source
Install [docker](https://www.docker.com/) on your machine.

[Clone the repository](https://github.com/NadavTasher/Contained/archive/master.zip), enter the extracted directory, then run the following commands:
```bash
docker build . -t openpush
docker run -p 80:80 --name openpush --restart unless-stopped -d openpush
```

## Contributing
Pull requests are welcome, but only for smaller changer.
For larger changes, open an issue so that we could discuss the change.

Bug reports and vulnerabilities are welcome too. 

## License
[MIT](https://choosealicense.com/licenses/mit/)