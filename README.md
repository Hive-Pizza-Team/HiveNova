# HiveNova - Space empire building browser game

![MOON_Discord_Event_Banner](https://github.com/user-attachments/assets/96607107-b195-4164-9537-241430acc86e)

Play the game at https://moon.hive.pizza

---

## The Game

The open-source game framework is based on [2Moons](https://gitter.im/2MoonsGame/Lobby/).

Code is located at [https://github.com/Hive-Pizza-Team/HiveNova](https://github.com/Hive-Pizza-Team/HiveNova) repository. It is a fork of [jkroepke/2Moons](https://github.com/jkroepke/2Moons) and SteemNova 2 (https://github.com/steemnova/steemnova) for Hive community purposes. HiveNova repository is the core of the game code.

![badge_powered-by-hive_dark](https://github.com/user-attachments/assets/803e396c-f165-40de-936c-03dd624153ad)

## Repository Structure

- [cache] - temporary cached server .tpl webpages
- [chat] - AJAX ingame client-side chat
- [includes]
  - game engine
  - configuration
  - administration
  - database scheme
  - external libraries
  - webpages functionality
- [install]
  - first installation
  - database creation
- [language] - translations: DE, EN, ES, FR, PL, PT, RU, TR
- [licenses] - open source license schemes
- [scripts] - client-side web browser .js scripts
- [styles] 
  - webpages .css templates
  - webpages .tpl templates
  - fonts
  - images
- [tests]


## Roadmap

* Hive Keychain
* Hive-Engine
* Discord
  

## Local installation

- Clone the repo
- Install components: `apt install apache2 php7.3 php7.3-gd php7.3-fpm php7.3-mysql php7.3-curl php-ds libapache2-mod mysql-server`
- Setup mysql: `create user USER identified by PASSWORD; create database DB; grant all privileges on DB.* to USER;`
- Set write privileges to dirs: `cache/`, `includes/`
- Run wizard: `127.0.0.1/install/install.php`

### If you run HiveNova on NGINX - Read nginx.md file!

## Screenshots

![screenshot](https://github.com/user-attachments/assets/3705e3c5-540c-4915-9f1b-8d4e2c6142ae)

## Copyright and License

HiveNova is a fork of the Open Source Game Framework [jkroepke/2Moons](https://github.com/jkroepke/2Moons) framework.
Background image created by [@mkdrwal](https://hive.blog/@mkdrwal)

HiveNova relies on the Ogame Probabilistic Battle Engine [(OPBE)](https://github.com/jstar88/opbe).

* 2Moons code copyright 2009-2016 Jan-Otto Kröpke released under the MIT License.
* OPBE code copyright 2013 Jstar released under the AGPLv3 License.
* Code copyright 2018 @steemnova released under the MIT License.
* Code copyright 2018-2020 @IntinteDAO released under the MIT License.
* Code copyright 07.05.2020-2020 @IntinteDAO released under the AGPLv3 License
* Code copyright 2025 @TeamMithril released under the AGPLv3 License
