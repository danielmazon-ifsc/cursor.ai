@mod @mod_socialforum @_file_upload
Feature: Vote relevancy for social forum discussions ans posts
  In order indicate relevant discussions and posts to other students
  As a student
  I need to be able to indicate discussions and posts I found relevant and see other students' opinion

  @javascript
  Scenario: Test discussion order according to relevancy and time of creation
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Social Forum" to section "1" and I fill the form with:
      | Social Forum name | Test social forum name |
      | Social Forum type | Q and A social forum |
      | Description | Test social forum description |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    When I add a new question to "Test social forum name" social forum with:
      | Subject | Question 1 by student 1 |
      | Message | This is the body |
    Then I should see "Question 1 by student 1" in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And "Relevant" "link" should not exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[1]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    When I add a new question to "Test social forum name" social forum with:
      | Subject | Question 2 by student 1 |
      | Message | This is the body |    
    Then I should see "Question 2 by student 1" in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And "Relevant" "link" should not exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[1]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And I should see "Question 1 by student 1" in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And "Relevant" "link" should not exist in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[2]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    Then I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I click on "Test social forum name" "link"
    And I click on "Question 1 by student 1" "link"
    And I click on "Relevant" "link"
    And I click on "Test social forum name" "link"
    Then I should see "Question 1 by student 1" in the "//tr[contains(@class,'discussion relevant r0')]" "xpath_element"
    And "Relevant" "link" should not exist in the "//tr[contains(@class,'discussion relevant r0')]" "xpath_element"
    And "//img[contains(@class,'isrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion relevant r0')]" "xpath_element"
    And I should see "1" in the "(//div[contains(@class,'numvotes')])[1]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "//tr[contains(@class,'discussion relevant r0')]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion relevant r0')]" "xpath_element"
    And I should see "Question 2 by student 1" in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And "Relevant" "link" should not exist in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[2]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And I click on "Question 1 by student 1" "link"
    And I click on "Irrelevant" "link"
    And I click on "Test social forum name" "link"
    Then I should see "Question 2 by student 1" in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And "Relevant" "link" should not exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[1]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And I should see "Question 1 by student 1" in the "//tr[contains(@class,'discussion irrelevant r1')]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion irrelevant r1')]" "xpath_element"
    And "Relevant" "link" should not exist in the "//tr[contains(@class,'discussion irrelevant r1')]" "xpath_element"
    And I should see "-1" in the "(//div[contains(@class,'numvotes')])[2]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "//tr[contains(@class,'discussion irrelevant r1')]" "xpath_element"
    And "//img[contains(@class,'isirrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion irrelevant r1')]" "xpath_element"
    And I click on "Question 1 by student 1" "link"
    And I click on "Irrelevant" "link"
    And I click on "Test social forum name" "link"
    Then I should see "Question 2 by student 1" in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And "Relevant" "link" should not exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[1]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r0')]" "xpath_element"
    And I should see "Question 1 by student 1" in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And "Relevant" "link" should not exist in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[2]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "//tr[contains(@class,'discussion r1')]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "//tr[contains(@class,'discussion r1')]" "xpath_element"

  @javascript
  Scenario: Test post order according to relevancy and time of creation
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Social Forum" to section "1" and I fill the form with:
      | Social Forum name | Test social forum name |
      | Social Forum type | Q and A social forum |
      | Description | Test social forum description |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    When I add a new question to "Test social forum name" social forum with:
      | Subject | Question 1 by student 1 |
      | Message | This is the body |
    And I follow "Question 1 by student 1"
    Then I should see "Question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[1]" "xpath_element"
    And "Relevant" "link" should not exist in the "(//div[contains(@class,'forumpost')])[1]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[1]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[1]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "(//div[contains(@class,'forumpost')])[1]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[1]" "xpath_element"
    And I reply "Question 1 by student 1" post from "Test social forum name" social forum with:
      | Subject | Reply 1 to question 1 by student 1 |
      | Message | This is the body |
    Then I should see "Reply 1 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "Relevant" "link" should not exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[2]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I reply "Question 1 by student 1" post from "Test social forum name" social forum with:
      | Subject | Reply 2 to question 1 by student 1 |
      | Message | This is the body |
    Then I should see "Reply 2 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "Relevant" "link" should not exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[2]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I should see "Reply 1 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "Relevant" "link" should not exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[3]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    When I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I click on "Test social forum name" "link"
    And I click on "Question 1 by student 1" "link"
    Then I should see "Question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[1]" "xpath_element"
    And "Relevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[1]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[1]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[1]" "xpath_element"
    And "Irrelevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[1]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[1]" "xpath_element"
    And I should see "Reply 2 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "Relevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[2]" "xpath_element"
    And "Irrelevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I should see "Reply 1 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "Relevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[3]" "xpath_element"
    And "Irrelevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//div[contains(@class,'relevant')]" "xpath_element" should not exist
    And "//div[contains(@class,'irrelevant')]" "xpath_element" should not exist
    When I click on "Relevant" "link" in the "//div[contains(@aria-label,'Reply 1 to question 1 by student 1')]" "xpath_element"
    Then I should see "Reply 1 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "Relevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "//img[contains(@class,'isrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I should see "1" in the "(//div[contains(@class,'numvotes')])[2]" "xpath_element"
    And "Irrelevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I should see "Reply 2 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "Relevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[3]" "xpath_element"
    And "Irrelevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And I should see "Reply 1 to question 1 by student 1" in the "//div[contains(@class,'relevant')]" "xpath_element"
    And I should not see "Reply 2 to question 1 by student 1" in the "//div[contains(@class,'relevant')]" "xpath_element"
    And "//div[contains(@class,'irrelevant')]" "xpath_element" should not exist
    When I click on "Irrelevant" "link" in the "//div[contains(@aria-label,'Reply 1 to question 1 by student 1')]" "xpath_element"
    Then I should see "Reply 2 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "Relevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[2]" "xpath_element"
    And "Irrelevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I should see "Reply 1 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "Relevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And I should see "-1" in the "(//div[contains(@class,'numvotes')])[3]" "xpath_element"
    And "Irrelevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//img[contains(@class,'isirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And I should see "Reply 1 to question 1 by student 1" in the "//div[contains(@class,'irrelevant')]" "xpath_element"
    And I should not see "Reply 2 to question 1 by student 1" in the "//div[contains(@class,'irrelevant')]" "xpath_element"
    And I should not see "Reply 2 to question 1 by student 1" in the "//div[contains(@class,'relevant')]" "xpath_element"
    When I click on "Irrelevant" "link" in the "//div[contains(@aria-label,'Reply 1 to question 1 by student 1')]" "xpath_element"
    Then I should see "Reply 2 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "Relevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[2]" "xpath_element"
    And "Irrelevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I should see "Reply 1 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "Relevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[3]" "xpath_element"
    And "Irrelevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//div[contains(@class,'relevant')]" "xpath_element" should not exist
    And "//div[contains(@class,'irrelevant')]" "xpath_element" should not exist
    When I click on "Reply" "link" in the "(//div[contains(@class,'forumpost')])[2]" "xpath_element"
    And I set the field "Subject" to "Reply 1 to reply 2 to question 1 by student 1"
    And I set the field "Message" to "This is the body"
    And I press "Post to social forum"
    Then I should see "Reply 1 to reply 2 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "Relevant" "link" should not exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should not exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//div[contains(@class,'numvotes')" "xpath_element" should not exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "Irrelevant" "link" should not exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should not exist in the "(//div[contains(@class,'forumpost')])[3]" "xpath_element"
    And I should see "Reply 1 to question 1 by student 1" in the "(//div[contains(@class,'forumpost')])[4]" "xpath_element"
    And "Relevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[4]" "xpath_element"
    And "//img[contains(@class,'notrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[4]" "xpath_element"
    And I should see "0" in the "(//div[contains(@class,'numvotes')])[3]" "xpath_element"
    And "Irrelevant" "link" should exist in the "(//div[contains(@class,'forumpost')])[4]" "xpath_element"
    And "//img[contains(@class,'notirrelevant')]" "xpath_element" should exist in the "(//div[contains(@class,'forumpost')])[4]" "xpath_element"


 
