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
    Then the "Content" TinyMCE editor should preview guidance "My own words for this chapter."
    And the "Content" TinyMCE editor should not save "My own words for this chapter."
    And I press "Save changes"
    And I should see "Chapter text."
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
    Then the "Content" TinyMCE editor should preview guidance "Form groups before starting."
    And the "Content" TinyMCE editor should not save "Form groups before starting."
    # Undo and redo rebuild the token, which then has no preview until the editor gives it one again.
    And I click on the "Undo" button for the "Content" TinyMCE editor
    And I click on the "Redo" button for the "Content" TinyMCE editor
    And the "Content" TinyMCE editor should preview guidance "Form groups before starting."
    And I press "Save changes"
    And I should see "Form groups before starting."

  @javascript
  Scenario: Editing guidance updates its preview
    Given I am on the "Course book" "book activity" page logged in as teacher1
    And I turn editing mode on
    And I follow "Edit chapter \"1. Chapter 1\""
    And I expand all toolbars for the "Content" TinyMCE editor
    And I click on the "Teacher guidance" button for the "Content" TinyMCE editor
    And I click on "[role^='menuitem'][aria-label='Use a preset']" "css_element"
    And I click on "[role^='menuitem'][aria-label='Group work']" "css_element"
    When I switch to the "Content" TinyMCE editor iframe
    And I click on "div[data-edguidance]" "css_element"
    And I switch to the main frame
    And I set the field "Guidance" to "My own text"
    And I set the field "Teacher guidance" to "<p>Groups of three for this chapter.</p>"
    And I click on "Save changes" "button" in the "Teacher guidance" "dialogue"
    Then the "Content" TinyMCE editor should preview guidance "Groups of three for this chapter."
    And the "Content" TinyMCE editor should not save "Groups of three for this chapter."
    And I press "Save changes"
    And I should see "Groups of three for this chapter."
    And I should not see "Form groups before starting."

  @javascript
  Scenario: Guidance can be moved up and down past the text around it
    Given I am on the "Course book" "book activity" page logged in as teacher1
    And I turn editing mode on
    And I follow "Edit chapter \"1. Chapter 1\""
    And I expand all toolbars for the "Content" TinyMCE editor
    And I click on the "Teacher guidance" button for the "Content" TinyMCE editor
    And I click on "[role^='menuitem'][aria-label='Use a preset']" "css_element"
    And I click on "[role^='menuitem'][aria-label='Group work']" "css_element"
    And I press "Save changes"
    And "Form groups before starting." "text" should appear before "Chapter text." "text"
    When I follow "Edit chapter \"1. Chapter 1\""
    And I move teacher guidance "down" in the "Content" TinyMCE editor
    And I press "Save changes"
    Then "Chapter text." "text" should appear before "Form groups before starting." "text"
    And I follow "Edit chapter \"1. Chapter 1\""
    And I move teacher guidance "up" in the "Content" TinyMCE editor
    And I press "Save changes"
    And "Form groups before starting." "text" should appear before "Chapter text." "text"
