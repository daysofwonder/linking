Feature: PlayFab <-> PlayReal Linking Workflow
  This tests the Virtuos implementation of login to a PlayFab account and a PlayReal account,
  linking the two accounts together, resolving possible missing or wrong links, and picking the
  right PlayFab content.

  Accounts used:

  | Description                                        ||   PF ID   |  Device ID  |      Content      || PR ID | Login |
  |----------------------------------------------------||–----------|-------------|–––----------––––––||-------|-------|
  | linked PlayFab and PlayReal accounts               || 100000004 | device_AAAA | content 100000004 ||   4   | Yann  |
  | non linked PlayFab account                         || 100000011 | device_BBBB | content 100000011 ||       |       |
  | non linked PlayReal account                        ||           |             |                   ||   3   | Eric  |
  | PlayReal account linked to another PlayFab account || 100005555 | device_CCCC | content 100005555 ||   5   | Brice |
  | Created PlayReal account using the SSO             ||           |             |                   ||   6   | John  |

  Scenario: Login with PlayFab and PlayReal accounts that are already linked together
    Given I have a linked PlayFab account
    When I run the workflow
    Then I should get two accounts
    And My accounts should be linked together
    And My content should be the one from my PlayFab account

  Scenario: Login with a PlayFab and PlayReal accounts that are not linked to anything yet
    Given I have a non linked PlayFab account
    And I have a non linked PlayReal account
    When I run the workflow
    Then I should get two accounts
    And My accounts should be linked together
    And My content should be the one from my PlayFab account

  Scenario: Login to a PlayReal account that was already linked to another PlayFab account and choose to discard the current Content
    Given I have a non linked PlayFab account
    And I have a PlayReal account linked to another PlayFab account
    And I choose to discard the current content
    When I run the workflow
    Then I should get two accounts
    And My accounts should be linked together
    And I should be using the other PlayFab account
    And My content should be the one from my other PlayFab account
    And the content of the original PlayFab account should be cleared

  Scenario: Login to a PlayReal account that was already linked to another PlayFab account and choose keep the current Content
    Given I have a non linked PlayFab account
    And I have a PlayReal account linked to another PlayFab account
    And I choose to keep the current content
    When I run the workflow
    Then I should get two accounts
    And My accounts should be linked together
    And I should be using the original PlayFab account
    And My content should be the one from the original PlayFab account

  Scenario: Corner case 1: linked PlayReal account: the PlayReal account should win
    Given I have a linked PlayFab account
    And I have a PlayReal account linked to another PlayFab account
    When I run the workflow
    Then I should get two accounts
    And My accounts should be linked together
    And I should be using the other PlayFab account
    And The PlayReal account should be the one linked to my other PlayFab account
    And My content should be the one from my other PlayFab account

  Scenario: Corner case 2: orphan PlayReal account: the PlayFab account should win
    Given I have a linked PlayFab account
    And I have a non linked PlayReal account
    When I run the workflow
    Then I should get two accounts
    And My accounts should be linked together
    And I should be using the original PlayFab account
    And The PlayReal account should be the one linked to the original PlayFab account
    And My content should be the one from the original PlayFab account

  Scenario: Corner case 3: there is no input PlayReal account, only a linked PlayFab account: used the linked account
    Given I have a linked PlayFab account
    And I have no PlayReal account
    When I run the workflow
    Then I should get two accounts
    And My accounts should be linked together
    And My content should be the one from my PlayFab account

  Scenario: Corner case 4: there is no input PlayReal account, only a non-linked PlayFab account: login/create a new PlayReal account using the SSO
    Given I have a non linked PlayFab account
    And I have no PlayReal account
    When I run the workflow
    Then I should get two accounts
    And My accounts should be linked together
    And My content should be the one from my PlayFab account
