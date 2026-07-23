# This file is part of Moodle - http://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
#
# Developer tasks for enrol_adele. Run `make` or `make help` for an overview.

COMPONENT   := enrol_adele
PLUGIN_DIR  := adele
PLUGIN_PATH := enrol/$(PLUGIN_DIR)
VERSION     := $(shell sed -n "s/^\$$plugin->release *= *'\(.*\)';/\1/p" version.php)

# Path to a Moodle checkout used for linking and for PHPUnit. Override freely:
#   make test MOODLE_DIR=/var/www/moodle
MOODLE_DIR  ?= ../moodle

BUILD_DIR   := build
ZIP_NAME    := $(COMPONENT)-$(VERSION).zip

# Files and directories that go into a release ZIP. Everything else (CI config,
# docs, build artefacts, VCS metadata) stays out.
DIST_CONTENT := classes db lang tests lib.php settings.php version.php \
                README.md CHANGELOG.md LICENSE.md

.DEFAULT_GOAL := help
.PHONY: help zip clean link unlink lint phpcs phpcbf phpmd phpdoc test ci checks

help: ## Show this help.
	@echo "$(COMPONENT) $(VERSION)"
	@echo ""
	@echo "Targets:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Variables:"
	@echo "  MOODLE_DIR   Moodle checkout to work against (currently: $(MOODLE_DIR))"

zip: clean ## Build an installable ZIP in build/.
	@mkdir -p $(BUILD_DIR)/$(PLUGIN_DIR)
	@cp -r $(DIST_CONTENT) $(BUILD_DIR)/$(PLUGIN_DIR)/
	@cd $(BUILD_DIR) && zip -rq $(ZIP_NAME) $(PLUGIN_DIR) \
		-x '*.DS_Store' -x '*/.git/*'
	@rm -rf $(BUILD_DIR)/$(PLUGIN_DIR)
	@echo "Built $(BUILD_DIR)/$(ZIP_NAME)"
	@echo "Install via Site administration > Plugins > Install plugins, type 'Enrolment method'."

clean: ## Remove build artefacts.
	@rm -rf $(BUILD_DIR)

link: ## Symlink this checkout into MOODLE_DIR/enrol/adele.
	@test -d "$(MOODLE_DIR)" || { echo "MOODLE_DIR '$(MOODLE_DIR)' not found."; exit 1; }
	@test -e "$(MOODLE_DIR)/$(PLUGIN_PATH)" \
		&& { echo "$(MOODLE_DIR)/$(PLUGIN_PATH) already exists."; exit 1; } || true
	@ln -s "$(CURDIR)" "$(MOODLE_DIR)/$(PLUGIN_PATH)"
	@echo "Linked $(CURDIR) -> $(MOODLE_DIR)/$(PLUGIN_PATH)"
	@echo "Now visit the notifications page to run the install."

unlink: ## Remove the symlink created by 'make link'.
	@test -L "$(MOODLE_DIR)/$(PLUGIN_PATH)" \
		|| { echo "Not a symlink: $(MOODLE_DIR)/$(PLUGIN_PATH) - refusing."; exit 1; }
	@rm "$(MOODLE_DIR)/$(PLUGIN_PATH)"
	@echo "Unlinked $(MOODLE_DIR)/$(PLUGIN_PATH)"

lint: ## Check PHP syntax.
	@moodle-plugin-ci phplint . || { \
		echo "moodle-plugin-ci not found, falling back to php -l"; \
		find . -path ./$(BUILD_DIR) -prune -o -name '*.php' -print \
			| xargs -n1 php -l > /dev/null; }

phpcs: ## Run the Moodle code checker.
	@moodle-plugin-ci phpcs --max-warnings 0 .

phpcbf: ## Auto-fix what the code checker can fix.
	@moodle-plugin-ci phpcbf .

phpmd: ## Run PHP Mess Detector.
	@moodle-plugin-ci phpmd .

phpdoc: ## Check PHPDoc blocks.
	@moodle-plugin-ci phpdoc --max-warnings 0 .

test: ## Run the PHPUnit tests of this plugin.
	@test -f "$(MOODLE_DIR)/vendor/bin/phpunit" \
		|| { echo "No PHPUnit in $(MOODLE_DIR). Run the Moodle PHPUnit init first."; exit 1; }
	@cd "$(MOODLE_DIR)" && vendor/bin/phpunit --testsuite $(COMPONENT)_testsuite

checks: lint phpcs phpdoc phpmd ## Run every static check.

ci: checks test ## Run the full local CI chain.
