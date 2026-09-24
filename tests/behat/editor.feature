@local @local_edguidance @editor_tiny
Feature: Adding teacher guidance from the editor
  In order to add guidance where it is needed
  As an editing teacher
  I need to embed it from the editor, from a site preset or my own words

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tina      | Teacher  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | idnumber | name        |
      | book     | C1     | book1    | Course book |
    # contentformat 1 is FORMAT_HTML. The generator's default is FORMAT_MOODLE, which TinyMCE does
    # not edit - Moodle would quietly hand the chapter to another editor, with no button at all.
    And the following "mod_book > chapters" exist:
      | book        | title     | content              | contentformat |
      | Course book | Chapter 1 | <p>Chapter text.</p> | 1             |
    And the following config values are set as admin:
      | config          | value                               | plugin           |
      | presettitle1    | Group work                          | local_edguidance |
      | presetguidance1 | <p>Form groups before starting.</p> | local_edguidance |

  @javascript
  Scenario: Start with blank
    Given I am on the "Course book" "book activity" page logged in as teacher1
    And I turn editing mode on
    And I follow "Edit chapter \"1. Chapter 1\""
    When I expand all toolbars for the "Content" TinyMCE editor
    And I click on the "Teacher guidance" button for the "Content" TinyMCE editor
    And I click on "[role^='menuitem'][aria-label='Start with blank']" "css_element"
    And I set the field "Teacher guidance" to "<p>My own words for this chapter.</p>"
    And I click on "Save changes" "button" in the "Teacher guidance" "dialogue"
    And I press "Save changes"
    Then I should see "Chapter text."
    And I should see "My own words for this chapter."

  @javascript
  Scenario: Use a preset
    Given I am on the "Course book" "book activity" page logged in as teacher1
    And I turn editing mode on
    And I follow "Edit chapter \"1. Chapter 1\""
    When I expand all toolbars for the "Content" TinyMCE editor
    And I click on the "Teacher guidance" button for the "Content" TinyMCE editor
    And I click on "[role^='menuitem'][aria-label='Use a preset']" "css_element"
    And I click on "[role^='menuitem'][aria-label='Group work']" "css_element"
    And I press "Save changes"
    Then I should see "Form groups before starting."
