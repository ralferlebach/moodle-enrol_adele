# Makefile for enrol_adele
# Mirrors the moodle-plugin-ci check suite used in GitHub Actions.
#
# Targets:
#   make all          — fix + full check suite (default)
#   make fix          — auto-fix PHP style + PHPDoc + rebuild AMD
#   make check        — check-only (no auto-fix)
#   make clear        — clear terminal
#
# Individual checks:
#   make lint-php     — PHPCS Moodle coding standard
#   make lint-phpdoc  — Moodle PHPDoc checker
#   make lint-js      — ESLint on AMD source files (skipped when amd/src/ is empty)
#   make lint-mustache — Mustache template syntax (skipped when templates/ is empty)
#   make lint-cpd     — PHP Copy/Paste Detector (informational)
#   make lint-md      — PHP Mess Detector (informational)
#
# Auto-fixers:
#   make fix-lint-php — phpcbf PHP code-style auto-fix
#   make fix-phpdoc   — moodlecheck PHPDoc report (skipped when tools/fix_phpdoc.php is absent)
#   make amd          — rebuild AMD minified files
#
# Tests:
#   make phpunit      — PHPUnit testsuite for this plugin
#
# Packaging & dev-checkout linking (enrol_adele-specific, not part of the
# shared template this file is otherwise adapted from):
#   make zip          — build an installable ZIP in build/
#   make clean        — remove build artefacts
#   make link         — symlink this checkout into MOODLE_ROOT/enrol/adele
#   make unlink       — remove that symlink
#
# Paths are auto-detected from the makefile's own location.
# The plugin lives at <MOODLE_ROOT>/enrol/adele/ — always two
# levels below the Moodle root — so both PLUGIN_DIR and MOODLE_ROOT are
# derived automatically and work on any installation.
# Override on the command line if necessary:
#   make lint-php MOODLE_ROOT=/opt/moodle

THIS_DIR      := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
PLUGIN_DIR    ?= $(THIS_DIR)
MOODLE_ROOT   ?= $(abspath $(PLUGIN_DIR)/../..)
PLUGIN_NAME   ?= enrol_adele
PLUGIN_REL    ?= enrol/adele
PLUGIN_BASENAME := $(notdir $(PLUGIN_REL))
VERSION       := $(shell sed -n "s/^\$$plugin->release *= *'\(.*\)';/\1/p" $(PLUGIN_DIR)/version.php)
PHP           ?= $(shell which php 2>/dev/null || echo /usr/bin/php)
PHPCS         ?= phpcs
PHPCBF        ?= phpcbf
NPX           ?= npx

# enrol_adele-specific: release ZIP build directory and contents. Everything
# else (CI config, docs, build artefacts, VCS metadata) stays out — mirrors
# .gitattributes' export-ignore list.
BUILD_DIR     := $(PLUGIN_DIR)/build
ZIP_NAME      := $(PLUGIN_NAME)-$(VERSION).zip
DIST_CONTENT  := classes db lang tests lib.php settings.php version.php \
                 README.md CHANGELOG.md LICENSE.md

.PHONY: all fix check clear \
        lint-php lint-phpdoc lint-js lint-mustache lint-cpd lint-md \
        fix-lint-php fix-phpdoc amd phpunit \
        zip clean link unlink

all: clear fix check
	@echo ""
	@echo "=== All checks complete. Review output above for errors. ==="

fix: clear fix-phpdoc fix-lint-php amd
	@echo ""
	@echo "=== All fixes complete. ==="

check: clear lint-php lint-phpdoc lint-mustache lint-cpd phpunit
	@echo ""
	@echo "=== All checks complete. Review output above for errors. ==="

clear:
	clear

lint-php:
	@echo "=== phpcs (Moodle standard, excludes tools/) ==="
	-cd $(PLUGIN_DIR) && $(PHPCS) \
		--standard=moodle \
		--extensions=php \
		--severity=1 \
		--no-cache \
		--ignore=tools/ \
		.

fix-lint-php:
	@echo ""
	@echo "=== phpcbf (auto-fix) ==="
	-cd $(PLUGIN_DIR) && $(PHPCBF) \
		--standard=moodle \
		--extensions=php \
		.

lint-phpdoc:
	@echo ""
	@echo "=== PHPDoc (local_moodlecheck, excludes tools/) ==="
	-cd $(MOODLE_ROOT) && $(PHP) local/moodlecheck/cli/moodlecheck.php \
		--path=$(PLUGIN_REL) \
		--exclude=$(PLUGIN_REL)/tools \
		--format=text 2>&1 | grep -B1 '    Line' | grep -v '^--$$' || true

fix-phpdoc:
	@echo ""
	@echo "=== fix_phpdoc (tools/fix_phpdoc.php) ==="
	@if [ -f $(PLUGIN_DIR)/tools/fix_phpdoc.php ]; then \
		$(PHP) $(PLUGIN_DIR)/tools/fix_phpdoc.php $(PLUGIN_DIR); \
	else \
		echo "No tools/fix_phpdoc.php in this plugin — skipped."; \
	fi

lint-mustache:
	@echo ""
	@echo "=== Mustache syntax check ==="
	@if [ -f $(PLUGIN_DIR)/tools/mustache_check.php ] && [ -d $(PLUGIN_DIR)/templates ]; then \
		$(PHP) $(PLUGIN_DIR)/tools/mustache_check.php \
			$(PLUGIN_DIR)/templates 2>&1 | grep -v '^OK:' || true; \
	else \
		echo "No templates/ (or no tools/mustache_check.php) in this plugin — skipped."; \
	fi

lint-cpd:
	@echo ""
	@echo "=== PHP Copy/Paste Detector ==="
	-cd $(PLUGIN_DIR) && phpcpd --min-lines 5 --min-tokens 70 . || true

lint-md:
	@echo ""
	@echo "=== PHP Mess Detector ==="
	-cd $(PLUGIN_DIR) && phpmd . text \
		cleancode,codesize,controversial,design,naming,unusedcode \
		--exclude tests,tools || true

lint-js:
	@echo ""
	@echo "=== ESLint (skipped when amd/src/ is empty) ==="
	@if ls $(PLUGIN_DIR)/amd/src/*.js 2>/dev/null | grep -q .; then \
		cd $(MOODLE_ROOT) && $(NPX) grunt eslint --root=. \
			--files=$(PLUGIN_REL)/amd/src/ --show-lint-warnings; \
	else \
		echo "No AMD source files — ESLint skipped."; \
	fi

amd:
	@echo ""
	@echo "=== AMD rebuild (skipped when amd/src/ is empty) ==="
	@if ls $(PLUGIN_DIR)/amd/src/*.js 2>/dev/null | grep -q .; then \
		files=$$(find $(PLUGIN_REL)/amd/src -name '*.js' \
			| tr '\n' ',' | sed 's/,$$//'); \
		cd $(MOODLE_ROOT) && $(NPX) grunt amd --root=. --force --files="$$files"; \
	else \
		echo "No AMD source files — skipped."; \
	fi

phpunit:
	@echo ""
	@echo "=== PHPUnit ==="
	@if ! $(PHP) -r \
		"define('CLI_SCRIPT',1); require '$(MOODLE_ROOT)/config.php'; \
		exit(empty(\$$CFG->phpunit_dataroot) ? 1 : 0);" 2>/dev/null; then \
		echo "SKIP: phpunit_dataroot not configured."; \
		echo "      Add to config.php: \$$CFG->phpunit_dataroot = '...';"; \
	else \
		reinit_check=$$(cd $(MOODLE_ROOT) && $(PHP) vendor/bin/phpunit \
			--testsuite $(PLUGIN_NAME)_testsuite \
			--testdox 2>&1 | head -5); \
		if printf '%s\n' "$$reinit_check" | grep -q "initialised for different version"; then \
			echo "PHPUnit environment outdated — reinitialising..."; \
			cd $(MOODLE_ROOT) && $(PHP) admin/tool/phpunit/cli/init.php; \
		fi; \
		tmpout=$$(mktemp); \
		cd $(MOODLE_ROOT) && $(PHP) vendor/bin/phpunit \
			--testsuite $(PLUGIN_NAME)_testsuite \
			--testdox > "$$tmpout" 2>&1; \
		phpunit_exit=$$?; \
		grep -v "^ ✔\|^ ✓\|^ ↩" "$$tmpout" || true; \
		rm -f "$$tmpout"; \
		exit $$phpunit_exit; \
	fi

# --- enrol_adele-specific: packaging & dev-checkout linking ---
# Not part of the shared mod_elang-derived template above; kept from the
# plugin's original Makefile because nothing above replaces this capability.

zip: clean ## Build an installable ZIP in build/.
	@mkdir -p $(BUILD_DIR)/$(PLUGIN_BASENAME)
	@cp -r $(addprefix $(PLUGIN_DIR)/,$(DIST_CONTENT)) $(BUILD_DIR)/$(PLUGIN_BASENAME)/
	@cd $(BUILD_DIR) && zip -rq $(ZIP_NAME) $(PLUGIN_BASENAME) \
		-x '*.DS_Store' -x '*/.git/*'
	@rm -rf $(BUILD_DIR)/$(PLUGIN_BASENAME)
	@echo "Built $(BUILD_DIR)/$(ZIP_NAME)"
	@echo "Install via Site administration > Plugins > Install plugins, type 'Enrolment method'."

clean: ## Remove build artefacts.
	@rm -rf $(BUILD_DIR)

link: ## Symlink this checkout into MOODLE_ROOT/enrol/adele.
	@test -d "$(MOODLE_ROOT)" || { echo "MOODLE_ROOT '$(MOODLE_ROOT)' not found."; exit 1; }
	@test -e "$(MOODLE_ROOT)/$(PLUGIN_REL)" \
		&& { echo "$(MOODLE_ROOT)/$(PLUGIN_REL) already exists."; exit 1; } || true
	@ln -s "$(PLUGIN_DIR)" "$(MOODLE_ROOT)/$(PLUGIN_REL)"
	@echo "Linked $(PLUGIN_DIR) -> $(MOODLE_ROOT)/$(PLUGIN_REL)"
	@echo "Now visit the notifications page to run the install."

unlink: ## Remove the symlink created by 'make link'.
	@test -L "$(MOODLE_ROOT)/$(PLUGIN_REL)" \
		|| { echo "Not a symlink: $(MOODLE_ROOT)/$(PLUGIN_REL) - refusing."; exit 1; }
	@rm "$(MOODLE_ROOT)/$(PLUGIN_REL)"
	@echo "Unlinked $(MOODLE_ROOT)/$(PLUGIN_REL)"
