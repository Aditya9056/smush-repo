# README #

This README would normally document whatever steps are necessary to get your application up and running.

** Make sure to remove README from release, this is for Bitbucket usage only **

### WP Smush - .org version and PRO version ###

* There isn't much difference between the .org version and the WPMU DEV version. So don't be confused, it's just few headers are different like, the Plugin Name, WDP ID is only stated in Pro version and the copyright text.

So it's important that, you don't merge pro branch to master, although you can pull master/dev to pro.

** Release Process **

Develop on dev branch, make whatever changes you want, if it's going to be a pro only feature, make sure you include proper check in code, as the codebase is same for free and pro version.

After you're done with the final changes and ready to release, push the code to dev branch, checkout to pro branch in your local and pull the dev and merge the code. Ideally there shouldn't be any conflict at all.

Push the code to pro branch. 

After proper testing, follow the release process and release the pro plugin with same versioning as on .org

For .org release, sync the code to your .org svn repo in local, and follow the release process for .org version

Don't forget to create a tag for the release and push it on bitbucket.

### Who do I talk to? ###

* You can contact Umesh Kumar <umesh@incsub.com> or Aaron Edwards