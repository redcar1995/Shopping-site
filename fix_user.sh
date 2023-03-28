#!/usr/bin/env sh

# Note: You should also update the [user] group in .git/config
# Note: This does not play nicely with remotes. You will need to blow them away and start over :(
# ref: https://stackoverflow.com/questions/750172/change-the-author-and-committer-name-and-e-mail-of-multiple-commits-in-git

git filter-branch -f --env-filter  '
OLD_EMAIL="alex.scheider26@gmail.com"
CORRECT_NAME="redcar1995"
CORRECT_EMAIL="benjamin1995202022@gmail.com
"
if [ "$GIT_COMMITTER_EMAIL" = "$OLD_EMAIL" ]
then
    export GIT_COMMITTER_NAME="$CORRECT_NAME"
    export GIT_COMMITTER_EMAIL="$CORRECT_EMAIL"
fi
if [ "$GIT_AUTHOR_EMAIL" = "$OLD_EMAIL" ]
then
    export GIT_AUTHOR_NAME="$CORRECT_NAME"
    export GIT_AUTHOR_EMAIL="$CORRECT_EMAIL"
fi
' --tag-name-filter cat -- --branches --tags