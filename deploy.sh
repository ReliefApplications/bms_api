#!/bin/bash

if [[ $1 == "prod" ]]; then
    ec2="ec2-35-158-182-63.eu-central-1.compute.amazonaws.com"
elif [[ $1 == "staging" ]]; then
    ec2="ec2-52-57-90-156.eu-central-1.compute.amazonaws.com"
else
    echo "Unknown environment"
    exit
fi
if [ -z `ssh-keygen -F $ec2` ]; then
  ssh-keyscan -H $ec2 >> ~/.ssh/known_hosts
fi

ssh -i $2 ubuntu@$ec2 \
    'cd /var/www/html/bms_api; \
    git pull origin dev; \
    sudo docker-compose exec -T php sf c:c; \
    sudo docker-compose exec  -T php sf d:m :m -n'