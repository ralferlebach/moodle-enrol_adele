#!/bin/bash
# PHPUnit of the three plugins, PostgreSQL then MariaDB.
cd /home/claude/moodlefix
for db in ${DBS:-pgsql mariadb}; do
  if [ $db = mariadb ]; then export ADELE_DB=mariadb; else unset ADELE_DB; fi
  php admin/tool/phpunit/cli/init.php > /tmp/pu_init_$db.log 2>&1
  for p in local/adele mod/adele enrol/adele; do
    echo "== $db $p"
    vendor/bin/phpunit --testsuite "$(echo $p | tr / _)_testsuite" > /tmp/pu_${db}_$(echo $p | tr / _).log 2>&1
    grep -E "^[0-9]+\) |^OK|^Tests:" /tmp/pu_${db}_$(echo $p | tr / _).log
  done
  echo "== $db release group"
  vendor/bin/phpunit --group adele_release > /tmp/pu_${db}_release.log 2>&1; grep -E "^OK|^Tests:" /tmp/pu_${db}_release.log
done
echo ALLDONE
