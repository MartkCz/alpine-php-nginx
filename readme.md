## Docker hub

Current PHP version 8.0.8

https://hub.docker.com/repository/docker/martkcz/php-nginx

## Usage with google app engine

app.yml
```yaml
runtime: custom
env: flex
```

Dockerfile
```dockerfile
FROM martkcz/php-nginx

# configs
# that is not necessary
COPY ./conf/nginx-app.conf /etc/nginx/conf.d/nginx-app.conf

## application files
COPY . /app
RUN chown -R www-data.www-data /app
```

## Nginx config
default /etc/nginx/conf.d/nginx-app.conf

```apacheconf
location / {
  # try to serve files directly, fallback to controller
  try_files $uri /index.php?$args;
}
```

## Customize with command line
Enables http -> https and www -> non-www redirection
```dockerfile
CMD ["/startup/run.bash", "--https", "--non-www"]
```

### Options

```
Options:
      --non-www                                Redirects www => non-www.
      --port=PORT                              Sets port. [default: 8080]
      --https                                  Redirects http => https.
      --cache-css-js                           Cache css and js for long time.
      --cache-media                            Cache images, icons, video audio, HTC for long time.
      --xdebug                                 Enables xdebug.
      --xdebug-profiler=XDEBUG-PROFILER        Enables xdebug profiler. [default: "/dev/null"]
      --memory-limit=MEMORY-LIMIT              Sets php memory limit. [default: "64M"]
      --max-execution-time=MAX-EXECUTION-TIME  Sets php max execution time. [default: 30]
      --max-input-time=MAX-INPUT-TIME          Sets php max input time. [default: 30]
      --mkdir=MKDIR                            Makes directory.
```

## Development with docker-compose

```yaml
version: '3.3'

services:
  app:
    image: martkcz/php-nginx
    ports:
      - "80:8080"
    restart: always
    command: /startup/run.bash --xdebug --memory-limit="256M" --max-execution-time="60" --mkdir="/app/var/log" --mkdir="/app/var/tmp"
    volumes:
      - "./:/app"
```
