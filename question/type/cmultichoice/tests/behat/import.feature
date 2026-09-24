@qtype @qtype_cmultichoice
Feature: Test importing Competency Multichoice questions
  As a teacher
  In order to reuse Competency Multichoice questions
  I need to import them

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
    And the following lp "frameworks" exist:
      | shortname   | idnumber |
      | Framework 1 | FW1 |
    And the following lp "competencies" exist:
      | shortname    | framework |
      | Competency 1 | FW1       |
      | Competency 2 | FW1       |

  @javascript @_file_upload
  Scenario: import Competency Multichoice question.
    When I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Competencies"
    And I click on "Add competencies to course" "button"
    And I select "Competency 1" of the competency tree
    And I click on "Add" "button" in the "Competency picker" "dialogue"
    And I click on "Add competencies to course" "button"
    And I select "Competency 2" of the competency tree
    And I click on "Add" "button" in the "Competency picker" "dialogue"
    And I am on site homepage
    And I follow "Course 1"
    And I navigate to "Import" node in "Course administration > Question bank"
    And I set the field "id_format_xml" to "1"
    And I upload "question/type/cmultichoice/tests/fixtures/testquestion.moodle.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 1 questions from file"
    And I should see "1. Find the capital cities in Europe."
    And I press "Continue"
    And I should see "Multi-choice-001"
    And I click on "Edit" "link" in the "Multi-choice-001" "table_row"
    And I expand all fieldsets
    Then I should see "Competency 1"
