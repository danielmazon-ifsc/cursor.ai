@qtype @qtype_cmultichoice
Feature: Test creating a Competency Multichoice question
  As a teacher
  In order to test my students
  I need to be able to create a Competency Multichoice question

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | T1        | Teacher1 | teacher1@moodle.com |
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
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Competencies"
    And I click on "Add competencies to course" "button"
    And I select "Competency 1" of the competency tree
    And I click on "Add" "button" in the "Competency picker" "dialogue"
    And I click on "Add competencies to course" "button"
    And I select "Competency 2" of the competency tree
    And I click on "Add" "button" in the "Competency picker" "dialogue"
    And I log out

  @javascript
  Scenario: Create a Competency Multichoice question with multiple response
    When I log in as "teacher1"
    And I follow "Course 1"
    And I navigate to "Question bank" node in "Course administration"
    And I add a "Competency Multichoice" question filling the form with:
      | Question name            | Multi-choice-001                   |
      | Question text            | Find the capital cities in Europe. |
      | General feedback         | Paris and London                   |
      | One or multiple answers? | Multiple answers allowed           |
      | Choice 1                 | Tokyo                              |
      | Choice 2                 | Spain                              |
      | Choice 3                 | London                             |
      | Choice 4                 | Barcelona                          |
      | Choice 5                 | Paris                              |
      | id_fraction_0            | None                               |
      | id_fraction_1            | None                               |
      | id_fraction_2            | 50%                                |
      | id_fraction_3            | None                               |
      | id_fraction_4            | 50%                                |
      | Hint 1                   | First hint                         |
      | Hint 2                   | Second hint                        |
      | Question competencies    | Competency 2                       |
    And I click on "Edit" "link" in the "Multi-choice-001" "table_row"
    And I expand all fieldsets
    Then I should not see "Competency 1"
    And I should see "Competency 2"

  @javascript
  Scenario: Create a Competency Multichoice question with single response
    When I log in as "teacher1"
    And I follow "Course 1"
    And I add a "Competency Multichoice" question filling the form with:
      | Question name            | Multi-choice-002                       |
      | Question text            | Find the capital city of England.      |
      | General feedback         | London is the capital city of England. |
      | One or multiple answers? | One answer only                        |
      | Choice 1                 | Manchester                             |
      | Choice 2                 | Buckingham                             |
      | Choice 3                 | London                                 |
      | Choice 4                 | Barcelona                              |
      | Choice 5                 | Paris                                  |
      | id_fraction_0            | None                                   |
      | id_fraction_1            | None                                   |
      | id_fraction_2            | 100%                                   |
      | id_fraction_3            | None                                   |
      | id_fraction_4            | None                                   |
      | Hint 1                   | First hint                             |
      | Hint 2                   | Second hint                            |
      | Question competencies    | Competency 1                           |
    And I click on "Edit" "link" in the "Multi-choice-002" "table_row"
    And I expand all fieldsets
    Then I should see "Competency 1"
    And I should not see "Competency 2"
