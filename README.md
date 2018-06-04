# WP Smush

This README would normally document whatever steps are necessary to get your application up and running.

** Make sure to remove README from release, this is for Bitbucket usage only **

## PRO and wp.org versions

* There isn't much difference between the .org version and the WPMU DEV version. So don't be confused, it's just few headers are different like, the Plugin Name, WDP ID is only stated in Pro version and the copyright text.

So it's important that, you don't merge pro branch to master, although you can pull master/dev to pro.

** Release Process **


Maintain two separate projects on local, wp-smushit and wp-smush-pro. wp-smushit contains master and dev branch, wp-smush-pro is cloned only from pro branch.


Develop on dev branch, make whatever changes you want, if it's going to be a pro only feature, make sure you include proper check in code, as the codebase is same for free and pro version.

After you're done with the final changes and ready to release, push the code to dev branch.

For free version, merge it in Master, For pro version, go to folder wp-smush-pro, pull code from dev branch. Resolve any conflicts, for pot file and readme, accept the pro branch code.

Push the code to pro branch. 

After proper testing, follow the release process and release the pro plugin with same versioning as on .org.

## Directory Naming

**The pro version uses the wp-smush-pro directory name, it's important that is what is in the zip file!**

For .org release, first update the local svn repo from .org, as their might be changes in readme.txt file, Copy that to your git repo. Sync the code to your .org svn repo in local, and follow the release process for .org version. The .org version uses the wp-smushit directory slug that we can't change.

Don't forget to create a tag for the release and push it on bitbucket.

## Who do I talk to?

* You can contact Umesh Kumar <umesh@incsub.com> or Aaron Edwards