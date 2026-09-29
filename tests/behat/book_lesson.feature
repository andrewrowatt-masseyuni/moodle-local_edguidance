@local @local_edguidance
Feature: Teacher guidance embedded in book chapters and lesson pages
  In order to guide teachers through a book or lesson
  As a teacher
  I need to see guidance embedded in its chapters and pages, and students must not

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tina      | Teacher  |
      | student1 | Sam       | Student  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name        |
      | book     | C1     | book1    | Course book |
      | lesson   | C1     | lesson1  | The lesson  |
    And the following "mod_book > chapters" exist:
      | book        | title     | content                                                                                     |
      | Course book | Chapter 1 | <p>Chapter text.</p><div class="edguidance-embed" data-edguidance="1111111111111111"></div> |
    And the following "mod_lesson > pages" exist:
      | lesson     | qtype   | title  | content                                                                                  |
      | The lesson | content | Page 1 | <p>Page text.</p><div class="edguidance-embed" data-edguidance="2222222222222222"></div> |
    And the following "mod_lesson > answers" exist:
      | page   | answer   | jumpto    |
      | Page 1 | Continue | Next page |
    And the following "local_edguidance > blocks" exist:
      | activity | embedkey         | guidance                                |
      | book1    | 1111111111111111 | <p>Pause here for the group task.</p>   |
      | lesson1  | 2222222222222222 | <p>Students often skip this page.</p>   |

  Scenario: A teacher sees chapter and page guidance; a student sees only the text
    When I am on the "Course book" "book activity" page logged in as teacher1
    Then I should see "Chapter text."
    And I should see "Pause here for the group task."
    And I am on the "The lesson" "lesson activity" page
    And I should see "Page text."
    And I should see "Students often skip this page."
    And I am on the "Course book" "book activity" page logged in as student1
    And I should see "Chapter text."
    And I should not see "Pause here for the group task."
    And I am on the "The lesson" "lesson activity" page
    And I should see "Page text."
    And I should not see "Students often skip this page."

  @javascript
  Scenario: Chapter guidance can be dismissed and restored like any other
    Given I am on the "Course book" "book activity" page logged in as teacher1
    When I click on "Mark as read" "button"
    Then I should see "When you revisit this page, it will be removed."
    And I should not see "Pause here for the group task."
    And I reload the page
    And I should see "Chapter text."
    And I should not see "Pause here for the group task."
    And I am on the "Course 1" "local_edguidance > dismissed guidance" page
    And I click on "a[aria-label='Restore teacher guidance in Course book']" "css_element"
    And I am on the "Course book" "book activity" page
    And I should see "Pause here for the group task."
