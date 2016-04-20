# bump-all

Permet de mettre à jour plusieurs projets d'une organisation github en même temps de de faire les PR

```bash
php app/console composer:update GITHUB_TOKEN ORGANISATION DEPENDENCY VERSION
```

## with docker

1/ Build the image

```bash
docker build -t bumper:latest .
```

2/ Run the command

```bash
docker run -it --rm --env GITHUB_TOKEN="<your_github_token>" bumper:latest bumper "<organisation>" "<dependency>" "<version>"
```

#### Pro-tip: use an alias

create the alias in your .(ba|z)shrc

```bash
alias bumper='docker run -it --rm --env GITHUB_TOKEN="<yout_github_token>" bumper:latest bumper'
```

You can now simply use ```bumper "<organisation>" "<dependency>" "<version>"``` to bump your dependencies
