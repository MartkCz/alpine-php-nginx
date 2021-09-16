all: build push

build:
	docker build -t martkcz/alpine-php-nginx:8.0 .

push:
	docker push martkcz/alpine-php-nginx:8.0
