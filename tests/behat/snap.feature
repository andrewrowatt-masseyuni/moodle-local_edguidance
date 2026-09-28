@local @local_edguidance @theme_snap
Feature: Teacher guidance in Snap activity cards
  In order to use teacher guidance on the Massey theme
  As a teacher
  I need to see it in Snap's activity cards too

  # Section 0 because Snap opens a course on its first section and shows no other until asked.
  Background:
    Given the following config values are set as admin:
      | theme | snap |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tina      | Teacher  |
      | student1 | Sam       | Student  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | section | idnumber | name        | intro                                                                                          | showdescription |
      | assign   | C1     | 0       | shown    | Shown task  | <p>Read this first.</p><div class="edguidance-embed" data-edguidance="0123456789abcdef"></div> | 1               |
      | assign   | C1     | 0       | hidden   | Hidden task | <div class="edguidance-embed" data-edguidance="fedcba9876543210"></div>                        | 0               |
    And the following "local_edguidance > blocks" exist:
      | activity | embedkey         | guidance                                  | introorder |
      | shown    | 0123456789abcdef | <p>Check the reading list is current.</p> | 1          |
      | hidden   | fedcba9876543210 | <p>Set the release date first.</p>        | 1          |

  @javascript
  Scenario: Teachers see both kinds of card guidance in Snap; students see neither
    When I am on the "Course 1" course page logged in as teacher1
    Then I should see "Check the reading list is current."
    And I should see "Set the release date first."
    And I am on the "Course 1" course page logged in as student1
    And I should see "Read this first."
    And I should not see "Check the reading list is current."
    And I should not see "Set the release date first."

  @javascript
  Scenario: Guidance on a Snap page card shows once, and dismissing it does not open the page
    # Snap shows a page's description in its card even with "Display description" off, so the
    # afterlink copy and the description copy would both appear without the de-duplication.
    Given the following "activities" exist:
      | activity | course | section | idnumber | name         | intro                                                                   | showdescription | content         |
      | page     | C1     | 0       | readme   | Reading page | <div class="edguidance-embed" data-edguidance="bbbbbbbbbbbbbbbb"></div> | 0               | <p>Page body</p> |
    And the following "local_edguidance > blocks" exist:
      | activity | embedkey         | guidance                         | introorder |
      | readme   | bbbbbbbbbbbbbbbb | <p>Walk through this together.</p> | 1          |
    When I am on the "Course 1" course page logged in as teacher1
    Then "//*[contains(@class, 'edguidance-body')][contains(., 'Walk through this together.')]" "xpath_element" should exist
    And I should see "Walk through this together."
    # Dismissing one block hides one block: if the guidance had been shown twice, the other copy
    # would still be on the page and this would fail.
    And I click on "Dismiss" "button" in the "//li[contains(@class, 'modtype_page')]" "xpath_element"
    And I should not see "Walk through this together."
    And I should see "Course 1" in the "h1" "css_element"
    And I click on "Review teacher guidance for this activity" "button" in the "//li[contains(@class, 'modtype_page')]" "xpath_element"
    And I should see "Walk through this together."

  @javascript
  Scenario: Guidance in a section summary in Snap, dismissed and brought back
    Given the following "local_edguidance > section blocks" exist:
      | course | section | embedkey         | guidance                          | summary                                                                                     |
      | C1     | 0       | cccccccccccccccc | <p>Introduce yourself first.</p> | <p>Welcome all.</p><div class="edguidance-embed" data-edguidance="cccccccccccccccc"></div> |
    When I am on the "Course 1" course page logged in as teacher1
    Then I should see "Welcome all."
    And I should see "Introduce yourself first."
    And I click on "Dismiss" "button" in the ".summary" "css_element"
    And I should not see "Introduce yourself first."
    And I click on "Review teacher guidance for this section" "button"
    And I should see "Introduce yourself first."
    And I am on the "Course 1" course page logged in as student1
    And I should see "Welcome all."
    And I should not see "Introduce yourself first."
