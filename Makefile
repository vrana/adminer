ROOT_DIRECTORY = $(shell dirname "$(realpath $(lastword $(MAKEFILE_LIST)))")
PHP := $(shell which php)
PORT := 8000


.DEFAULT_GOAL := default


.PHONY: default
default: compile

.PHONY: compile
compile:
	$(PHP) $(ROOT_DIRECTORY)/compile.php

.PHONY: server
server:
	php \
	  --server 127.0.0.1:$(PORT) \
	  --docroot $(ROOT_DIRECTORY)

.PHONY: initialize
initialize:
	git \
	  -C $(ROOT_DIRECTORY) \
	  submodule \
	  update \
	  --init \
	  --recursive

.PHONY: clean
clean:
	rm \
	  --recursive \
	  --force \
	  $(ROOT_DIRECTORY)/adminer.php

.PHONY: clean.all
clean.all: clean
