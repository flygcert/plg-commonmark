#!/bin/bash
set -e

message() {
	echo -e "\n# --------------------------------------------------------------------"
	echo "# $1"
	echo "# ----------------------------------------  - -- ---  -- --- - - --"
}

getReleaseVersion() {
	local ini_file
	ini_file="$(cd "$(dirname "$0")/.." && pwd)/jorobo.ini"

	[[ ! -f "$ini_file" ]] && echo "ERROR: File not found: $ini_file" && exit 1

	RELEASE_VERSION=$(awk -F '=' '
		/^[[:space:]]*version[[:space:]]*=/ {
			value = $2
			gsub(/^[[:space:]]+|[[:space:]]+$/, "", value)
			print value
			exit
		}
	' "$ini_file")

	[[ -z "$RELEASE_VERSION" ]] && echo "ERROR: Could not read version from $ini_file" && exit 1
	LATEST_TAG=$(git describe --tags "$(git rev-list --tags --max-count=1)")
}

message ">>> Starting release"

[[ ! -x "$(command -v gh)" ]] && echo "gh not found, you need to install github CLI" && exit 1

gh auth status

[[ $(git rev-parse --abbrev-ref HEAD) != "develop" ]] && echo "ERROR: Checkout to develop" && exit 1

[[ $(git status --porcelain) ]] && echo "ERROR: The branch is not clean, commit your changes before creating the release" && exit 1

message ">>> Pulling develop"
git pull origin develop ##
message ">>> Pulling tags"
git fetch --prune --prune-tags origin

getReleaseVersion

message ">>> Release: $RELEASE_VERSION"

read -r -p "Last release version was '$LATEST_TAG', do you want to create '$RELEASE_VERSION' [Y/n]:  " RESPONSE
if [[ $RESPONSE =~ ^([yY][eE][sS]|[yY])$ ]]; then
	BRANCH_NAME="release/$RELEASE_VERSION"
	message ">>>>> Creating branch '$BRANCH_NAME' from develop..."

	git checkout -b "$BRANCH_NAME" develop
	git push origin "$BRANCH_NAME"
	gh pr create --base develop --head "$BRANCH_NAME" --title "Release - $RELEASE_VERSION" --fill
else
	message "Action cancelled exiting..."
	exit 1
fi
