# WP Smush

Before starting development make sure you read and understand everything in this README.

## Working with Git

Clone the plugin repo and checkout the `release/<version>` branch

```
# git clone git@bitbucket.org:incsub/wp-smushit.git --recursive
# git fetch && git checkout release/<version>
```

Install/update the necessary submodules if the branch is already checked out

```
# git submodule init --
# git submodule update  
```

When switching between versions, it might be necessary to force update the submodules

```
# git submodule update --remote --force
```

## Installing dependencies and initial configuration

Install Node
```
# curl -sL https://deb.nodesource.com/setup_10.x | sudo -E bash -
# sudo apt-get install -y nodejs build-essential
```

Install the necessary npm modules and packages
```
# npm install
``` 

Set up username and email for Git commits
```
# git config user.email "<your email>"
# git config user.name "<your name>"
```

## Build tasks (npm)

Everything (except unit tests) should be handled by npm. Note that you don't need to interact with Grunt in a direct way.

Command | Action
------- | ------
`npm run watch` | Start watching JS files
`npm run compile` | Compile assets
`npm run translate` | Build pot file inside /languages/ folder
`npm run build` | Build both versions, useful to provide packages to QA without doing all the release tasks
`npm run build:pro` | Build only pro version
`npm run build:free` | Build only wp.org version

**IMPORTANT!** After branch checkout, you need to run `npm run build` or `npm run compile` in order to build the assets
(minified versions of css and js files). Precompiled assets are not included with the development version of the plugin.
This is done so that the git commits are clean and do not include the built assets that are regenerated with every
change in the css/js files.

## Versioning

Follow semantic versions [http://semver.org/](http://semver.org/) as `package.json` won't work otherwise. That's it:

- `X.X.0` for major versions
- `X.X.X` for minor versions
- `X.X[.X||.0]-rc.1` for release candidates
- `X.X[.X||.0]-beta.1` for betas (QA builds)
- `X.X[.X||.0]-alpha.1` for alphas (design check tasks)

## Workflow

Do not commit on `master` branch (should always be synced with the latest released version). `release/<version>` is the code that accumulates all the code for a release.

- Create a new branch from `release/<version>` branch: `git checkout -b <branch-name>`. Try to give it a descriptive name. For example:
    * `release/version` for next releases
    * `new/feature` for new features
    * `enhance/enhancement` for enhancements
    * `fix/issue` or `bug/issue` for bug fixing;
- Make your commits and push the new branch: `git push -u origin <branch-name>`;
- File the new Pull Request against `release/<version>` branch;
- Assign lead developer or another developer to review your code;
- Once the PR is approved and finished, merge it in `release/<version>` branch;
- Delete your branch locally and make sure that it does not longer exist remote.

It's a good idea to create the Pull Request as soon as possible so everybody knows what's going on with the project
from the PRs screen in Bitbucket.

If developing a PRO only feature, make sure you include proper check in code, as the codebase is same for wp.org and PRO versions.

## How to release PRO version

Prior to release, code needs to be checked and tested by QA team. Merge all active Pull Requests into `release/<version>`.
Build the release with `npm run build` script and upload the zip files to QA.

The release process always must start on `master` branch. Once QA gives green light to release, latest changes from `relase/<version>`
branch are merged into `master`.

Follow these steps to make the release:

* Edit `.changelog` file. Grunt will extract it and put the contents in `changelog.txt` and `readme.txt`
* Adjust `Tested up to` in `readme.txt`
* Once you have your `release/<version>` branch ready, merge into `master`. Do not forget to update the version number. Always with
format X.X.X. You'll need to update in `wp-smush.php` (header and `WP_SMUSH_VERSION` constant) and also `package.json`
* Execute `npm run build`. zips and files will be generated in `build` folder
* Tag new version on git with `git tag -a <version> -m "<changelog>"` and `git push -u origin <version>`

## How to release FREE version

Free releases are released to wp.org a week after a successful Pro release.

At this point, all the code should be already in the master branch. Make sure that the readme.txt file is in sync with the latest changes from wp.org (if not, merge the changes to master branch).

Follow these steps to make the release:

* Checkout `master` branch and build the packages with `npm run build`
* Make sure that there is no extra code with `git status`
* Just to play it safe, upgrade all the git submodules with `git submodule update --remote --force`
* Execute the deploy script `bash misc/deploy-to-svn.sh`
* Verify the `trunk` and `tags` releases in `build/smush-svn` folder (pay special attention to versions in readme.txt and wp-smush.php files)
* If everything is good navigate to `build/smush-svn` and deploy to SVN with `svn ci -m "Release <version>"`

## Difference between versions

PRO and wp.org versions are exactly the same, except some header strings, PRO version includes `core/external/dash-notice`,
while wp.org contains the `core/external/free-dashboard` folder. Also the PRO version has the `changelog.txt` file while the
wp.org version has `readme.txt`.

## Directory Naming

* `wp-smush-pro` PRO version
* `wp-smushit` wp.org version

## Documentation ##
Documentation is available here:
https://docs.google.com/document/d/1IdYywXU3rFAVjugl-Y4jL2t9EFIuQK5cZXA0s1rR89Q/edit?usp=sharing
