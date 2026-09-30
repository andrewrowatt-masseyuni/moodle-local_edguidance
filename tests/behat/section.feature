@local @local_edguidance
Feature: Teacher guidance in section summaries
  In order to know how a week or topic is meant to be run
  As a teacher
  I need to see guidance in its section summary, where students cannot

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format | numsections |
      | Course 1 | C1        | topics | 2           |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Editing   | Teacher  |
      | teacher2 | Other     | Teacher  |
      | student1 | Sam       | Student  |
    # Writing guidance is for managers; editing teachers only work through it.
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | manager        |
      | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |
    And the following "local_edguidance > section blocks" exist:
      | course | section | embedkey         | guidance                              | summary                                                                                          |
      | C1     | 1       | 0123456789abcdef | <p>Release the quiz after Friday.</p> | <p>This week we read.</p><div class="edguidance-embed" data-edguidance="0123456789abcdef"></div> |
    And the following config values are set as admin:
      | config          | value                               | plugin           |
      | presettitle1    | Group work                          | local_edguidance |
      | presetguidance1 | <p>Form groups before starting.</p> | local_edguidance |

  Scenario: Teachers see a section's guidance and students do not
    When I am on the "Course 1" course page logged in as teacher2
    Then I should see "This week we read."
    And I should see "Release the quiz after Friday."
    And I should see "Only teachers see this"
    And I log out
    And I am on the "Course 1" course page logged in as student1
    And I should see "This week we read."
    And I should not see "Release the quiz after Friday."

  @javascript @editor_tiny
  Scenario: Add guidance to a section summary from the editor
    Given I am on the "Course 1" course page logged in as teacher1
    And I turn editing mode on
    And I edit the section "2"
    When I expand all toolbars for the "Description" TinyMCE editor
    And I click on the "Teacher guidance" button for the "Description" TinyMCE editor
    And I click on "[role^='menuitem'][aria-label='Use a preset']" "css_element"
    And I click on "[role^='menuitem'][aria-label='Group work']" "css_element"
    And I press "Save changes"
    Then I should see "Form groups before starting."
    And I log out
    And I am on the "Course 1" course page logged in as student1
    And I should not see "Form groups before starting."
