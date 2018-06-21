# WP Smush

Before starting development make sure you read and understand everything in this README.

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

## Versioning

Follow semantic versioning [http://semver.org/](http://semver.org/) as `package.json` won't work otherwise. That's it:

- `X.X.0` for mayor versions
- `X.X.X` for minor versions
- `X.X[.X||.0]-rc.1` for release candidates
- `X.X[.X||.0]-beta.1` for betas (QA builds)
- `X.X[.X||.0]-alpha.1` for alphas (design check tasks)

## Workflow

Do not commit on `master` branch (should always be synced with the latest released version). `dev` is the code
that accumulates all the code for the next version. If multiple versions are developed at the same time, `qa` branch
should contains code that is being tested by QA team.

- Create a new branch from `dev` branch: `git checkout -b branch-name`. Try to give it a descriptive name. For example:
    * `release/X.X.X` for next releases
    * `new/some-feature` for new features
    * `enhance/some-enhancement` for enhancements
    * `fix/some-bug` for bug fixing
- Make your commits and push the new branch: `git push -u origin branch-name`
- File the new Pull Request against `dev` branch
- Assign somebody to review your code.
- Once the PR is approved and finished, merge it in `dev` branch.
- Delete your branch locally and make sure that it does not longer exist remote.

It's a good idea to create the Pull Request as soon as possible so everybody knows what's going on with the project
from the PRs screen in Bitbucket.

If developing a PRO only feature, make sure you include proper check in code, as the codebase is same for wp.org and PRO versions.

## How to release PRO and wp.org versions

Prior to release, code needs to be checked and tested by QA team. Merge all active Pull Requests into `dev` branch and
sync to `qa` branch. Build the release with `npm run build` script and send the zip files to QA.

The release process always must start on `master` branch. Once QA gives green light to release, latest changes from `qa`
branch are merged into `master`.

Follow these steps to make the release:

* Edit `.changelog` file. Grunt will extract it and put the contents in `changelog.txt` and `readme.txt`.
* Once you have your `dev` branch ready, merge into `master`. Do not forget to update the version number. Always with
format X.X.X. You'll need to update in `wp-smush.php` (header and $version variable) and also `package.json`
* Execute `npm run build`. zips and files will be generated in `build` folder.
* Do not forget to sync `master` on `dev` by checking out `dev` branch and then `git merge master`

## Difference between versions

PRO and wp.org versions are exactly the same, except some header strings, PRO version includes `extras/dash-notice`,
while wp.org contains the `extras/free-dashboard` folder.

## Directory Naming

* `wp-smush-pro` PRO version
* `wp-smush` wp.org version

## Who do I talk to?

* You can contact Umesh Kumar <umesh@incsub.com> or Aaron Edwards