@qtype @qtype_cmultichoice
Feature: Test exporting Competency Multichoice questions
  As a teacher
  In order to be able to reuse my Competency Multichoice questions
  I need to export them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | T1        | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype         | name             | template    |
      | Test questions   | cmultichoice  | Multi-choice-001 | two_of_four |
      | Test questions   | cmultichoice  | Multi-choice-002 | one_of_four |
    And the following lp "frameworks" exist:
      | shortname   | idnumber |
      | Framework 1 | FW1 |
    And the following lp "competencies" exist:
      | shortname    | framework |
      | Competency 1 | FW1       |
      | Competency 2 | FW1       |

  @javascript
  Scenario: Export a Competency Multichoice question
    When I log in as "admin"
    And I am on site homepage
    And I follow "Course 1"
    And I navigate to "Question bank" node in "Course administration"
    And I click on "Edit" "link" in the "Multi-choice-001" "table_row"
    And I expand all fieldsets
    And I set the field "Question competencies" to "Competency 1"
    And I click on "Save changes" "button"
    And I am on site homepage
    And I follow "Course 1"
    And I navigate to "Export" node in "Course administration > Question bank"
    And I set the field "id_format_xml" to "1"
    And I press "Export questions to file"
    Then following "click here" should download between "4500" and "4600" bytes
    # If the download step is the last in the scenario then we can sometimes run
    # into the situation where the download page causes a http redirect but behat
    # has already conducted its reset (generating an error). By putting a logout
    # step we avoid behat doing the reset until we are off that page.
