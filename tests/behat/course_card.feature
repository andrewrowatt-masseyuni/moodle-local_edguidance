@local @local_edguidance
Feature: Teacher guidance in activity cards
  In order to know how an activity is meant to be run
  As a teacher
  I need to see its guidance in the activity card, and be able to put it out of the way

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
      | activity | course | idnumber | name        | intro                                                                                          | showdescription |
      | page     | C1     | shown    | Shown page  | <p>Read this first.</p><div class="edguidance-embed" data-edguidance="0123456789abcdef"></div> | 1               |
      | page     | C1     | hidden   | Hidden page | <div class="edguidance-embed" data-edguidance="fedcba9876543210"></div>                        | 0               |
    And the following "local_edguidance > blocks" exist:
      | activity | embedkey         | guidance                                  | introorder |
      | shown    | 0123456789abcdef | <p>Check the reading list is current.</p> | 1          |
      | hidden   | fedcba9876543210 | <p>Set the release date first.</p>        | 1          |

  Scenario: Teachers see guidance in the card whether or not the description is shown
    When I am on the "Course 1" course page logged in as teacher2
    Then I should see "Check the reading list is current." in the "Shown page" "activity"
    And I should see "Set the release date first." in the "Hidden page" "activity"
    And I should see "Only teachers see this"

  Scenario: Students see the description but none of the guidance
    When I am on the "Course 1" course page logged in as student1
    Then I should see "Read this first." in the "Shown page" "activity"
    And I should not see "Check the reading list is current."
    And I should not see "Set the release date first."
    And I should not see "Only teachers see this"

  @javascript
  Scenario: Dismissing guidance can be undone straight away
    Given I am on the "Course 1" course page logged in as teacher1
    When I click on "Mark as read" "button" in the "Hidden page" "activity"
    Then I should see "You have marked this teacher guidance as read." in the "Hidden page" "activity"
    And I should see "When you revisit this page, it will be removed." in the "Hidden page" "activity"
    And I should not see "Set the release date first."
    And I click on "Undo" "button" in the "Hidden page" "activity"
    And I should see "Set the release date first." in the "Hidden page" "activity"
    And I should not see "When you revisit this page, it will be removed."
    And I reload the page
    And I should see "Set the release date first." in the "Hidden page" "activity"

  @javascript
  Scenario: Dismissed guidance is gone on the next page load, and can be restored
    Given I am on the "Course 1" course page logged in as teacher1
    When I click on "Mark as read" "button" in the "Hidden page" "activity"
    And I click on "Mark as read" "button" in the "Shown page" "activity"
    And I reload the page
    # Nothing is left of it, not even the header - and the description around it is untouched.
    Then I should not see "Set the release date first."
    And I should not see "Check the reading list is current."
    And I should not see "Only teachers see this" in the "region-main" "region"
    And I should see "Read this first." in the "Shown page" "activity"
    And I am on the "Course 1" "local_edguidance > dismissed guidance" page
    And I should see "Set the release date first."
    And I should see "Check the reading list is current."
    And I click on "a[aria-label='Restore teacher guidance in Hidden page']" "css_element"
    And I should see "The teacher guidance has been restored."
    And I should not see "Set the release date first."
    And I should see "Check the reading list is current."
    And I am on the "Course 1" course page
    And I should see "Set the release date first." in the "Hidden page" "activity"
    And I should not see "Check the reading list is current."

  Scenario: The dismissed guidance page says when there is nothing to restore
    When I am on the "Course 1" "local_edguidance > dismissed guidance" page logged in as teacher1
    Then I should see "You have not marked any teacher guidance as read in this course."

  @javascript
  Scenario: Guidance shows its category and heading, and a task is marked as complete
    Given the following "activities" exist:
      | activity | course | idnumber | name         | intro                                                                   | showdescription |
      | page     | C1     | groups   | Groups page  | <div class="edguidance-embed" data-edguidance="bbbbbbbbbbbbbbbb"></div> | 1               |
      | page     | C1     | reading  | Reading page | <div class="edguidance-embed" data-edguidance="cccccccccccccccc"></div> | 1               |
    And the following "local_edguidance > blocks" exist:
      | activity | embedkey         | guidance                  | introorder | category       | heading         |
      | groups   | bbbbbbbbbbbbbbbb | <p>Set up the groups.</p> | 1          | task           | Before week one |
      | reading  | cccccccccccccccc | <p>Skim chapter two.</p>  | 1          | recommendation |                 |
    When I am on the "Course 1" course page logged in as teacher1
    # A heading names the guidance in place of its category; without one, the category does.
    Then I should see "Before week one" in the ".edguidance-task h5.edguidance-title" "css_element"
    And I should see "Recommendation" in the "Reading page" "activity"
    And "h5.edguidance-title" "css_element" should not exist in the "Reading page" "activity"
    And "Mark as read" "button" should not exist in the "Groups page" "activity"
    And "Mark as read" "button" should exist in the "Reading page" "activity"
    And I click on "Mark as complete" "button" in the "Groups page" "activity"
    And I should see "You have marked this task as complete." in the "Groups page" "activity"
    And I should not see "Set up the groups."

  @javascript
  Scenario: One teacher dismissing guidance does not hide it from another
    Given I am on the "Course 1" course page logged in as teacher1
    And I click on "Mark as read" "button" in the "Shown page" "activity"
    When I am on the "Course 1" course page logged in as teacher2
    Then I should see "Check the reading list is current." in the "Shown page" "activity"

  Scenario: Guidance using a site preset shows the preset as it is now
    Given the following config values are set as admin:
      | config          | value                         | plugin           |
      | presettitle1    | Release dates                 | local_edguidance |
      | presetguidance1 | <p>Original preset words.</p> | local_edguidance |
    And the following "activities" exist:
      | activity | course | idnumber | name         | intro                                                                   | showdescription |
      | page     | C1     | preset   | Preset page  | <div class="edguidance-embed" data-edguidance="aaaaaaaaaaaaaaaa"></div> | 1               |
    And the following "local_edguidance > blocks" exist:
      | activity | embedkey         | presetslot | introorder |
      | preset   | aaaaaaaaaaaaaaaa | 1          | 1          |
    And the following config values are set as admin:
      | config          | value                        | plugin           |
      | presetguidance1 | <p>Revised preset words.</p> | local_edguidance |
    When I am on the "Course 1" course page logged in as teacher1
    Then I should see "Revised preset words." in the "Preset page" "activity"
    And I should not see "Original preset words."
