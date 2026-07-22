ROOT_DIRECTORY := $(patsubst %/,%,$(dir $(realpath $(lastword $(MAKEFILE_LIST)))))
PHP := php
PORT := 8000


.DEFAULT_GOAL := default


.PHONY: default
default: compile

.PHONY: compile
compile:
	$(PHP) $(ROOT_DIRECTORY)/compile.php

.PHONY: server
server:
	$(PHP) \
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
	$(PHP) -r "array_map('unlink', array_merge(glob('$(ROOT_DIRECTORY)/adminer*.php'), glob('$(ROOT_DIRECTORY)/editor*.php')));"

.PHONY: clean.all
clean.all: clean
