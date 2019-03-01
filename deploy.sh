#!/bin/bash

if [[ $1 == "prod" ]]; then
    ec2="ubuntu@ec2-35-158-182-63.eu-central-1.compute.amazonaws.com"
elif [[ $1 == "staging" ]]; then
    ec2="ubuntu@ec2-52-57-90-156.eu-central-1.compute.amazonaws.com"
else
    echo "Unknown environment"
    exit
fi

ssh -o "StrictHostKeyChecking no" -i $2 $ec2 \ 
    "cd /var/www/html/bms_api; \
    git pull origin dev; \
    sudo docker-compose exec php sf c:c \
    sudo docker-compose exec php sf d:m :m -n"