@local @local_edguidance @editor_tiny
Feature: Working through teacher guidance in the editor
  In order to keep track of guidance while I edit an activity
  As an editing teacher
  I need to tick its checklists, and mark it as read or complete, in the editor, but not change it

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
    # contentformat 1 is FORMAT_HTML, which TinyMCE edits (see editor.feature).
    And the following "mod_book > chapters" exist:
      | book        | title     | content                                                                                                                                                              | contentformat |
      | Course book | Chapter 1 | <div class="edguidance-embed" data-edguidance="0123456789abcdef"></div><p>Chapter text.</p><div class="edguidance-embed" data-edguidance="fedcba9876543210"></div> | 1             |
    And the following "local_edguidance > blocks" exist:
      | activity | embedkey         | category | guidance                                            |
      | book1    | 0123456789abcdef | note     | <p>Read the chapter first.</p>                      |
      | book1    | fedcba9876543210 | task     | <p>[ ] Check the groups</p><p>[ ] Book the room</p> |

  @javascript
  Scenario: An editing teacher ticks and marks guidance in the editor, but cannot change it
    Given I am on the "Course book" "book activity" page logged in as teacher1
    And I turn editing mode on
    And I follow "Edit chapter \"1. Chapter 1\""
    And the "Content" TinyMCE editor should preview guidance "Read the chapter first."
    # Nothing to add guidance with, and a click on guidance does not open it for editing.
    And I expand all toolbars for the "Content" TinyMCE editor
    And I click on the "Teacher guidance" button for the "Content" TinyMCE editor
    And "[role^='menuitem'][aria-label='Start with blank']" "css_element" should not exist
    And I switch to the "Content" TinyMCE editor iframe
    And I click on "div[data-edguidance]" "css_element"
    And I switch to the main frame
    And "Teacher guidance" "dialogue" should not exist
    # Ticking and marking are saved as they are made, whatever becomes of the chapter.
    When I click on "Check the groups" in the teacher guidance preview in the "Content" TinyMCE editor
    And I click on "Mark as read" in the teacher guidance preview in the "Content" TinyMCE editor
    And I press "Cancel"
    Then I should see "Chapter text."
    And I should not see "Read the chapter first."
    And the field "Check the groups" matches value "1"
    And the field "Book the room" matches value "0"

  @javascript
  Scenario: Guidance marked as read can be restored from the editor
    Given I am on the "Course book" "book activity" page logged in as teacher1
    And I click on "Mark as read" "button"
    And I turn editing mode on
    And I follow "Edit chapter \"1. Chapter 1\""
    And the "Content" TinyMCE editor should not preview guidance "Read the chapter first."
    And I expand all toolbars for the "Content" TinyMCE editor
    And I click on the "Teacher guidance" button for the "Content" TinyMCE editor
    And I click on "[role^='menuitem'][aria-label='Show guidance marked as read']" "css_element"
    And the "Content" TinyMCE editor should preview dismissed guidance "Read the chapter first."
    When I click on "Restore" in the teacher guidance preview in the "Content" TinyMCE editor
    Then the "Content" TinyMCE editor should not preview dismissed guidance "Read the chapter first."
    And the "Content" TinyMCE editor should preview guidance "Read the chapter first."
    And I press "Cancel"
    And I should see "Read the chapter first."
