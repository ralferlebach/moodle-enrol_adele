@enrol @enrol_adele
Feature: Manage ADELE enrolment instances
  In order to recompute or hard-delete ADELE-owned enrolments without
  direct database access
  As an admin
  I need to see them listed on the management page

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

  Scenario: The management page lists a learning path that owns an ADELE instance
    Given an ADELE enrol instance exists in course "C1" for user "student1"
    And I log in as "admin"
    When I directly visit the url "enrol/adele/manage.php"
    Then I should see "Behat test path"
    And I should see "Recompute"
    And I should see "Hard delete"

  Scenario: Recomputing a learning path from the management page
    Given an ADELE enrol instance exists in course "C1" for user "student1"
    And I log in as "admin"
    And I directly visit the url "enrol/adele/manage.php"
    When I click on "Recompute" "button"
    Then I should see "Recomputed for"
