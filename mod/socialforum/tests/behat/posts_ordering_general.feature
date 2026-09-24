@mod @mod_socialforum
Feature: New discussions and discussions with recently added replies are displayed first
  In order to use social forum as a discussion tool
  As a user
  I need to see currently active discussions first

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname  | email                 |
      | teacher1  | Teacher   | 1         | teacher1@example.com  |
      | student1  | Student   | 1         | student1@example.com  |
    And the following "courses" exist:
      | fullname  | shortname | category  |
      | Course 1  | C1        | 0         |
    And the following "course enrolments" exist:
      | user      | course    | role            |
      | teacher1  | C1        | editingteacher  |
      | student1  | C1        | student         |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Social Forum" to section "1" and I fill the form with:
      | Social Forum name  | Course general social forum                |
      | Description | Single discussion social forum description |
      | Social Forum type  | Standard social forum for general use      |
    And I log out

  #
  # We need javascript/wait to prevent creation of the posts in the same second. The threads
  # would then ignore each other in the prev/next navigation as the Social Forum is unable to compute
  # the correct order.
  #
  @javascript
  Scenario: Replying to a social forum post or editing it puts the discussion to the front
    Given I log in as "student1"
    And I follow "Course 1"
    And I follow "Course general social forum"
    #
    # Add three posts into the social forum.
    #
    When I add a new discussion to "Course general social forum" social forum with:
      | Subject | Social Forum post 1            |
      | Message | This is the first post  |
    And I add a new discussion to "Course general social forum" social forum with:
      | Subject | Social Forum post 2            |
      | Message | This is the second post |
    And I add a new discussion to "Course general social forum" social forum with:
      | Subject | Social Forum post 3            |
      | Message | This is the third post  |
    #
    # Edit one of the social forum posts.
    #
    And I follow "Social Forum post 2"
    And I click on "Edit" "link" in the "//div[contains(concat(' ', normalize-space(@class), ' '), ' forumpost ')][contains(., 'Social Forum post 2')]" "xpath_element"
    And I set the following fields to these values:
      | Subject | Edited social forum post 2     |
    And I press "Save changes"
    And I wait to be redirected
    And I log out
    #
    # Reply to another social forum post.
    #
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Course general social forum"
    And I follow "Social Forum post 1"
    And I click on "Reply" "link" in the "//div[@aria-label='Social Forum post 1 by Student 1']" "xpath_element"
    And I set the following fields to these values:
      | Message | Reply to the first post |
    And I press "Post to social forum"
    And I wait to be redirected
    And I am on site homepage
    And I follow "Course 1"
    And I follow "Course general social forum"
    #
    # Make sure the order of the social forum posts is as expected (most recently participated first).
    #
    Then I should see "Social Forum post 3" in the "//tr[contains(concat(' ', normalize-space(@class), ' '), ' discussion ')][position()=3]" "xpath_element"
    And I should see "Edited social forum post 2" in the "//tr[contains(concat(' ', normalize-space(@class), ' '), ' discussion ')][position()=2]" "xpath_element"
    And I should see "Social Forum post 1" in the "//tr[contains(concat(' ', normalize-space(@class), ' '), ' discussion ')][position()=1]" "xpath_element"
    #
    # Make sure the next/prev navigation uses the same order of the posts.
    #
    And I follow "Edited social forum post 2"
    And "//a[@aria-label='Next discussion: Social Forum post 1']" "xpath_element" should exist
    And "//a[@aria-label='Previous discussion: Social Forum post 3']" "xpath_element" should exist
