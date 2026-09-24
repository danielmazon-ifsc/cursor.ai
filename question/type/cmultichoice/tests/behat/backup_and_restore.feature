@qtype @qtype_cmultichoice
Feature: Test duplicating a quiz containing a Competency Multichoice question
  As a teacher
  In order re-use my courses containing Competency Multichoice questions
  I need to be able to backup and restore them

  Background:
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype            | name             | template    |
      | Test questions   | cmultichoice     | Multi-choice-001 | two_of_four |
    And the following "activities" exist:
      | activity   | name      | course | idnumber |
      | quiz       | Test quiz | C1     | quiz1    |
    And quiz "Test quiz" contains the following questions:
      | Multi-choice-001 | 1 |
    And the following lp "frameworks" exist:
      | shortname   | idnumber |
      | Framework 1 | FW1 |
    And the following lp "competencies" exist:
      | shortname    | framework |
      | Competency 1 | FW1       |
      | Competency 2 | FW1       |
    And I log in as "admin"
    And I am on site homepage
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
    And I navigate to "Question bank" node in "Course administration"
    And I click on "Edit" "link" in the "Multi-choice-001" "table_row"
    And I expand all fieldsets
    And I set the field "Question competencies" to "Competency 1"
    And I click on "Save changes" "button"
    And I am on site homepage
    And I follow "Course 1"

  @javascript
  Scenario: Backup and restore a course containing a Competency Multichoice question
    When I backup "Course 1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | Course 2 |
    And I navigate to "Question bank" node in "Course administration"
    And I click on "Edit" "link" in the "Multi-choice-001" "table_row"
    Then the following fields match these values:
      | Question name                      | Multi-choice-001                   |
      | Question text                      | Which are the odd numbers?         |
      | General feedback                   | The odd numbers are One and Three. |
      | Default mark                       | 1                                  |
      | One or multiple answers?           | Multiple answers allowed           |
      | Shuffle the choices?               | 1                                  |
      | Choice 1                           | One                                |
      | Choice 2                           | Two                                |
      | Choice 3                           | Three                              |
      | Choice 4                           | Four                               |
      | id_fraction_0                      | 50%                                |
      | id_fraction_1                      | None                               |
      | id_fraction_2                      | 50%                                |
      | id_fraction_3                      | None                               |
      | For any correct response           | Well done!                         |
      | For any partially correct response | Parts, but only parts, of your response are correct. |
      | For any incorrect response         | That is not right at all.          |
    And I should see "Competency 1"
