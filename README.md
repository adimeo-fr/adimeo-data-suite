# Adimeo Data Suite

ADS is used to index data in an Elastic cluster. You can then use ADS functionalities to search over your data.

## Stack

- Symfony
- PHP

## Requirements

This project based on **docker** and **docker-compose**. Make sure docker and docker-compose are
installed on your machine.

It is also based on the [Adimeo dev stack](https://github.com/Core-Techs-Git/adimeo_docker_local#readme).
You need to ensure that this stack is installed and running on your machine.

## Development environment

### DNS

If you are using dnsmask, you can skip this section. Otherwise, here are the domains you'll need to add
to your hosts file (`/etc/hosts`):

- `ads.dev.local`: dns used to access to UI of APM;

### Initialize

#### Start

To start dev environment, use this command:

```
docker-compose up -d
```

If you already have the docker images in your machine and modifications have been made, you can
rebuild images with:


```sh
docker-compose up -d --build
```

#### Containers

This project comes with a few containers and services:

- service `web` (container named `ads_web`): contains the web server (an httpd server);
- service `elk` (container named `ads_elk`): contains the ElasticSearch Engine;

#### Dependencies

All the source codes are present on the containers. All the `vendor` directory is on the right container. If you are in 
the dev environment, you have a bind mount for the source code so that overrides the one on the container.
So you need to install the dependencies on your computer.

To do that, execute these commands:

```
docker-compose exec web composer install
```

Or with docker:

```
docker exec apm_web composer install
```

#### Configuration

You need to create this configuration and place it inside a `.env` file.

Here are all the configurations:

* `APP_ENV:` symfony environment (dev, prod, test, etc.) ;
* `APP_SECRET:` symfony secret ;
* `ELASTICSEARCH_SERVER_URL:` URL of the ElasticSearch server inside the container
    * Example: `ads_elk:9200`
* `STAT_ELASTICSEARCH_SERVER_URL:` URL to access ElasticSearch API directly.
    * Example: `ads_elk:9200`
* `RECO_ELASTICSEARCH_SERVER_URL:` TODO
    * Example: `TODO`
* `ADS_INDEX_NB_SHARDS:` TODO:
    * Example: `1`
* `ADS_INDEX_NB_REPLICAS:` TODO:
    * Example: `1`
* `ADS_STAT_INDEX_NB_SHARDS:` TODO:
    * Example: `1`
* `ADS_STAT_INDEX_NB_REPLICAS:` TODO:
    * Example: `1`
* `ADS_RECO_INDEX_NB_SHARDS:` TODO:
    * Example: `1`
* `ADS_RECO_INDEX_NB_REPLICAS:` TODO:
    * Example: `1`
* `ADS_API_APPLY_BOOSTING:` TODO:
    * Example: `0`
* `SYNONYMS_DICTIONARIES_PATH:` TODO:
    * Example: `TODO`
* `COLLECT_STATS:` TODO:
    * Example: `1`
* `IS_LEGACY:` Tell ADS if you use an old version of ElasticSearch:
    * Example: `1`
* `MAX_REPLICAS:` TODO
    * Example: `0`
* `SYNONYMS_DICTIONARIES_PATH:` TODO:
    * Example: `TODO`
* `TRUSTED_PROXIES:` TODO:
    * Example: `127.0.0.1,REMOTE_ADDR`

#### Terminal access

To access the server from your terminal, you can use this command from anywhere in your machine:

```sh
# get CONTAINER_ID from from docker ps command

docker exec -it CONTAINER_ID /bin/bash
```

Or from the root directory of the source code:

```sh
docker-compose exec SERVICE bash
```

Where service is a service inside docker-compose (web, elk, etc...).

#### Access

Open browser and go to `https://ads.dev.local/` to access ADS.