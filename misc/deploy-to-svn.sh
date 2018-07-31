#!/bin/bash

GIT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )/../" && pwd )
SVN_DIR="$GIT_DIR/build/smush-svn"
WPORG_BUILD_DIR="$GIT_DIR/build/wp-smushit"
ERROR_COLOR='\033[41m'
BLACK_COLOR='\033[40m'
COLOR_OFF='\033[0m'
INFO_COLOR='\033[42m'

cd $GIT_DIR

if [ ! -d "$WPORG_BUILD_DIR" ]; then
  echo -e "${ERROR_COLOR}The folder $WPORG_BUILD_DIR must exist first. Please run npm run build ${COLOR_OFF}"
  exit 0
fi

VERSION=`grep -m 1 "^ \* Version" $WPORG_BUILD_DIR/wp-smush.php | awk -F' ' '{print $3}' | sed 's/[[:space:]]//g'`

if [ -d "$SVN_DIR" ]; then
  rm -rf $SVN_DIR
fi

echo "Checking out SVN shallowly to $SVN_DIR"
svn checkout https://plugins.svn.wordpress.org/wp-smushit/ --depth=empty $SVN_DIR
echo "Done!"

cd $SVN_DIR

echo "Checking out SVN trunk to $SVN_DIR/trunk"
svn -q up trunk

echo "Checking out SVN tags shallowly to $SVN_DIR/tags"
svn -q up tags --depth=empty

echo "Deleting everything in trunk except for .svn directories"
for file in $(find $SVN_DIR/trunk/* -not -path "*.svn*"); do
	rm $file 2>/dev/null
done

echo "Rsync'ing everything over from $WPORG_BUILD_DIR"
rsync -r $WPORG_BUILD_DIR/* $SVN_DIR/trunk

echo "Purging .po files"
rm -f $SVN_DIR/trunk/languages/*.po

echo "Creating a new tag: $VERSION"
# Tag the release.
svn cp trunk tags/$VERSION

# Change stable tag in the tag itself, and commit (tags shouldn't be modified after comitted)
perl -pi -e "s/Stable tag: .*/Stable tag: $VERSION/" tags/$VERSION/readme.txt
perl -pi -e "s/Stable tag: .*/Stable tag: $VERSION/" trunk/readme.txt
# svn ci

svn add --force .

# Delete files that are no longer needed
svn st | grep '^!' | awk '{print $2}' | xargs svn del

echo -e "${INFO_COLOR}Success! SVN preparation tasks completed. Please review:"
echo -e "- Check that ${BLACK_COLOR}./build/smush-svn/tags/$VERSION${INFO_COLOR} is the correct tag generated"
echo -e "- ${BLACK_COLOR}./build/smush-svn/trunk/readme.txt${INFO_COLOR} Stable tag field has been updated and is correct"
echo -e "If everything is correct, navigate to ${BLACK_COLOR}./build/smush-svn${INFO_COLOR} and make a great commit!${COLOR_OFF}"
