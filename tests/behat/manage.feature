@enrol @enrol_adele
Feature: Manage ADELE enrolment instances
  In order to recompute or hard-delete ADELE-owned enrolments without
  direct database access
  As an admin
  I need to see them listed, filtered and paginated on the management page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  Scenario: The management page shows the empty state when nothing owns an ADELE instance
    Given I log in as "admin"
    When I directly visit the url "enrol/adele/manage.php"
    Then I should see "Learning path enrolment management"
    And I should see "No learning path currently owns any ADELE enrolment instance."

  Scenario: The management page lists an instance with its course and type
    Given an ADELE enrol instance exists in course "C1" for user "student1"
    And I log in as "admin"
    When I directly visit the url "enrol/adele/manage.php"
    Then I should see "Behat test path"
    And I should see "C1"
    And I should see "Target course"
    And I should see "Recompute"

  Scenario: The last reconciliation is reported as not yet run
    Given an ADELE enrol instance exists in course "C1" for user "student1"
    And I log in as "admin"
    When I directly visit the url "enrol/adele/manage.php"
    Then I should see "Last full reconciliation"
    And I should see "The scheduled reconciliation has not run yet"
    And I should see "No repairs are currently queued."

  Scenario: Recomputing a learning path from the management page
    Given an ADELE enrol instance exists in course "C1" for user "student1"
    And I log in as "admin"
    And I directly visit the url "enrol/adele/manage.php"
    When I click on "Recompute" "button"
    Then I should see "Recomputed for"

  Scenario: Hard delete is offered only once the list is narrowed to one learning path
    Given an ADELE enrol instance exists in course "C1" for user "student1"
    And I log in as "admin"
    When I directly visit the url "enrol/adele/manage.php"
    Then "Hard delete" "button" should not exist
    When I open the ADELE management page filtered to learning path "Behat test path"
    Then "Hard delete" "button" should exist
    And I should see "C1"

  Scenario: The instance list is paginated
    Given 60 ADELE enrol instances exist
    And I log in as "admin"
    When I directly visit the url "enrol/adele/manage.php"
    Then I should see "BULK1"
    And I should not see "BULK60"
    When I click on "Page 2" "link"
    Then I should see "BULK60"

  Scenario: Filtering by course narrows the list
    Given 60 ADELE enrol instances exist
    And I log in as "admin"
    And I directly visit the url "enrol/adele/manage.php"
    When I set the field "Course" to "BULK7"
    And I click on "Apply filter" "button"
    Then I should see "BULK7"
    And I should not see "BULK1 "

  Scenario: Filtering by type finds no host instances when there are none
    Given an ADELE enrol instance exists in course "C1" for user "student1"
    And I log in as "admin"
    And I directly visit the url "enrol/adele/manage.php"
    When I set the field "Type" to "Host course"
    And I click on "Apply filter" "button"
    Then I should see "No learning path currently owns any ADELE enrolment instance."
