#!/bin/sh

# Config
pluginName="RestApi"
outputFile="build/limesurvey-rest-api.zip"

# Exit on error
set -e

mainRepoDir="$(dirname "$(realpath "$0/..")")"
# The resulting file path
zipFilePath="$mainRepoDir/$outputFile"

cd "$mainRepoDir"

# Composer dev dependencies are huge.
echo "Removing Composer dev dependencies..."
composer --no-dev install > /dev/null 2>&1

# Remove previous zip file to prevent extra removed files to remain there
if [[ -e "$zipFilePath" ]]; then
    rm "$zipFilePath"
fi

# Create the zip file in the current directory
zip -r -y "$zipFilePath" "spec/" "src/" "vendor/" "config.xml" "LICENSE.md" "$pluginName.php" > /dev/null

echo "Zip file created successfully."

echo "Re-adding Composer dev dependencies..."
composer install > /dev/null 2>&1
