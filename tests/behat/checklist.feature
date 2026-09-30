@local @local_edguidance
Feature: Checklists in teacher guidance
  In order to keep track of what has been done in a course
  As a teacher
  I need to tick off the items on a guidance checklist, and see what my colleagues have ticked

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Editing   | Teacher  |
      | teacher2 | Other     | Teacher  |
      | student1 | Sam       | Student  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name        | intro                                                                                              | showdescription |
      | page     | C1     | setup    | Setup page  | <p>Read this first.</p><div class="edguidance-embed" data-edguidance="0123456789abcdef"></div> | 1               |
    # Items on lines of one paragraph, and in a paragraph of their own.
    And the following "local_edguidance > blocks" exist:
      | activity | embedkey         | category | introorder | guidance                                                                         |
      | setup    | 0123456789abcdef | task     | 1          | <p>[ ] Set the due date<br>[ ] Check the groups</p><p>[x] Book the room</p> |

  @javascript
  Scenario: A tick is shared with every teacher, and can be taken back
    Given I am on the "Course 1" course page logged in as teacher2
    And the field "Book the room" matches value "1"
    When I click on "Check the groups" "checkbox" in the "Setup page" "activity"
    And I reload the page
    Then the field "Check the groups" matches value "1"
    And the field "Set the due date" matches value "0"
    And I am on the "Course 1" course page logged in as teacher1
    And the field "Check the groups" matches value "1"
    And I click on "Check the groups" "checkbox" in the "Setup page" "activity"
    And I click on "Book the room" "checkbox" in the "Setup page" "activity"
    And I reload the page
    And the field "Check the groups" matches value "0"
    And the field "Book the room" matches value "0"
    # Ticking is not dismissing: the guidance is all still there.
    And I should see "Set the due date" in the "Setup page" "activity"

  @javascript
  Scenario: Guidance turns green once its whole checklist is ticked
    Given I am on the "Course 1" course page logged in as teacher2
    And ".edguidance-complete" "css_element" should not exist in the "Setup page" "activity"
    When I click on "Set the due date" "checkbox" in the "Setup page" "activity"
    And I click on "Check the groups" "checkbox" in the "Setup page" "activity"
    Then ".edguidance-complete" "css_element" should exist in the "Setup page" "activity"
    And I reload the page
    And ".edguidance-complete" "css_element" should exist in the "Setup page" "activity"
    And I click on "Book the room" "checkbox" in the "Setup page" "activity"
    And ".edguidance-complete" "css_element" should not exist in the "Setup page" "activity"

  Scenario: Students see none of the checklist
    When I am on the "Course 1" course page logged in as student1
    Then I should see "Read this first." in the "Setup page" "activity"
    And I should not see "Set the due date"
    And ".edguidance-check" "css_element" should not exist in the "Setup page" "activity"

  Scenario: A preset's checklist shows, but cannot be ticked
    Given the following config values are set as admin:
      | config          | value                                  | plugin           |
      | presettitle1    | Weekly checks                          | local_edguidance |
      | presetguidance1 | <ul><li>[ ] Answer the forum</li></ul> | local_edguidance |
    And the following "activities" exist:
      | activity | course | idnumber | name        | intro                                                                   | showdescription |
      | page     | C1     | preset   | Preset page | <div class="edguidance-embed" data-edguidance="aaaaaaaaaaaaaaaa"></div> | 1               |
    And the following "local_edguidance > blocks" exist:
      | activity | embedkey         | presetslot | introorder |
      | preset   | aaaaaaaaaaaaaaaa | 1          | 1          |
    When I am on the "Course 1" course page logged in as teacher1
    Then I should see "Answer the forum" in the "Preset page" "activity"
    And the "Answer the forum" "checkbox" should be disabled
