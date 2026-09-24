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
    And I should not see "Teacher guidance"

  @javascript
  Scenario: Dismissed guidance collapses to an icon that restores it
    Given I am on the "Course 1" course page logged in as teacher1
    When I click on "Dismiss" "button" in the "Hidden page" "activity"
    Then I should not see "Set the release date first."
    And "Review teacher guidance for this activity" "button" should exist in the "Hidden page" "activity"
    # Dismissal is personal and remembered.
    And I reload the page
    And I should not see "Set the release date first."
    And I should see "Check the reading list is current."
    And I click on "Review teacher guidance for this activity" "button" in the "Hidden page" "activity"
    And I should see "Set the release date first." in the "Hidden page" "activity"
    And I reload the page
    And I should see "Set the release date first." in the "Hidden page" "activity"

  @javascript
  Scenario: One teacher dismissing guidance does not hide it from another
    Given I am on the "Course 1" course page logged in as teacher1
    And I click on "Dismiss" "button" in the "Shown page" "activity"
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
