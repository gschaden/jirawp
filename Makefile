DOCKER_HUB_USER ?= $(USER)
TAG = $(DOCKER_HUB_USER)/jirawp
UNAME_S := $(shell uname -s)
ifeq ($(UNAME_S),Linux)
    INSTALL_COMPOSER = curl -sS https://getcomposer.org/installer \
		| sudo php -- --install-dir=/usr/local/bin --filename=composer
endif
ifeq ($(UNAME_S),Darwin)
    INSTALL_COMPOSER = brew install composer
endif


compose:
	$(INSTALL_COMPOSER)
	composer install

data:
	mkdir data/
	cat vendor/sabre/dav/examples/sql/sqlite.* | sqlite3 data/db.sqlite
	cat sql/sqlite.* | sqlite3 data/db.sqlite
	chmod -Rv a+rw data/

.htaccess: htaccess.sample
	cp htaccess.sample .htaccess

assemble: compose data .htaccess

run:
	docker-compose up --build

build: assemble
	docker build -t $(TAG) .

push: build
	docker push $(TAG)
