<?php

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Definition\Call\Given;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawDrupalContext implements SnippetAcceptingContext
{

  /**
   * Initializes context.
   *
   * Every scenario gets its own context instance.
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct() {
  }

  /**
   * Creates and authenticates a user with the given role via Drush.
   *
   * @Given /^I am logged in as a user named "(?P<username>[^"]*)" with the "(?P<role>[^"]*)" role that doesn't force password change$/
   */
  public function assertAuthenticatedByRole($username, $role) {

    $user = (object)array(
      'name' => $username,
      'pass' => $this->getRandom()->name(16),
      'role' => $role,
      'roles' => array($role),
    );
    $user->mail = "{$user->name}@example.com";
    // Create a new user.
    $this->userCreate($user);

    // Find the user
    $account = user_load_by_name($user->name);

    // Remove the "Force password change on next login" record.
    db_delete('password_policy_force_change')
      ->condition('uid', $account->uid)
      ->execute();
    db_delete('password_policy_expiration')
      ->condition('uid', $account->uid)
      ->execute();

    $this->login();

  }

  /**
   * @Then /^I logout$/
   */
  public function assertLogout() {
    $this->logout();
  }

  /**
   * @Given /^a user named "(?P<username>[^"]*)" with role "(?P<role>[^"]*)" exists$/
   */
  public function assertAccountCreated($username, $role) {
    if (!user_load_by_name($username)) {
      $user = (object)array(
        'name' => $username,
        'pass' => $this->getRandom()->name(16),
        'role' => $role,
        'roles' => array($role),
      );
      $user->mail = "{$user->name}@example.com";
      // Create a new user.
      $this->userCreate($user);
    }
  }

  /**
   * @Given /^I visit the user edit page for "(?P<username>[^"]*)"$/
   */
  public function iVisitTheUserEditPageFor($username) {
    $account = user_load_by_name($username);
    if (!empty($account->uid)) {
      $this->getSession()->visit($this->locatePath('/user/' . $account->uid . '/edit'));
    } else {
      throw new \Exception('No such user');
    }
  }

  /**
   * @Then /^I should be able to change the "(?P<role_name>[^"]*)" role$/
   */
  public function iShouldBeAbleToChangeTheRole($role_name) {
    $administrator_role = user_role_load_by_name($role_name);
    $this->assertSession()->elementExists('css', '#edit-roles-change-' . $administrator_role->rid);
  }

  /**
   * @Then /^I should not be able to change the "(?P<role_name>[^"]*)" role$/
   */
  public function iShouldNotBeAbleToChangeTheRole($role_name) {
    $administrator_role = user_role_load_by_name($role_name);
    $this->assertSession()->elementNotExists('css', '#edit-roles-change-' . $administrator_role->rid);
  }

  /**
   * Fills in WYSIWYG editor with specified id.
   *
   * @Given /^(?:|I )enter "(?P<text>[^"]*)" for WYSIWYG "(?P<iframe>[^"]*)"$/
   */
  public function iFillInInWYSIWYGEditor($text, $iframe) {
    try {
      $this->getSession()->switchToIFrame($iframe);
    } catch (Exception $e) {
      throw new \Exception(sprintf("No iframe with id '%s' found on the page '%s'.", $iframe, $this->getSession()->getCurrentUrl()));
    }
    $this->getSession()->executeScript("document.body.innerHTML = '<p>" . $text . "</p>'");
    $this->getSession()->switchToIFrame();
  }

  /**
   * @Given /^I should be able to block the user$/
   */
  public function iShouldBeAbleToBlockTheUser() {
    $this->assertSession()->elementExists('css', 'input[name=status]');
  }

  /**
   * @Given /^I should not be able to block the user$/
   */
  public function iShouldNotBeAbleToBlockTheUser() {
    $this->assertSession()->elementNotExists('css', 'input[name=status]');
  }


  /**
   * @Given /^I visit the user cancel page for "(?P<username>[^"]*)"$/
   */
  public function iShouldNotBeAbleToCancelTheAccount($username) {
    $account = user_load_by_name($username);
    return new Given('I visit "/user/' + $account->uid + '/cancel"', function () {
    });
  }

  /**
   * @Then /^I should be able to cancel the account "(?P<username>[^"]*)"$/
   */
  public function iShouldBeAbleToCancelTheAccount($username) {
    $this->selectUserVBOCheckbox($username);
    $this->getSession()->getPage()->fillField('operation', 'action::views_bulk_operations_user_cancel_action');
    $this->getSession()->getPage()->pressButton('edit-submit--2');
    $this->assertSession()->elementExists('css', 'input[value=Next][type=submit]');
    return new Given('I should not see "is protected from cancellation, and was not cancelled."');
  }

  /**
   * Selects a user in the VBO list.
   *
   * @param string $username
   *
   * @throws \InvalidArgumentException
   *   When no such username exists or the checkbox can't be found.
   */
  protected function selectUserVBOCheckbox($username) {
    if ($account = user_load_by_name($username)) {
      if ($checkbox = $this->getSession()->getPage()->find('css', 'input[value=' . $account->uid . ']')) {
        $checkbox->check();
      } else {
        throw new \InvalidArgumentException(sprintf('No such checkbox %s', $username));
      }
    } else {
      throw new \InvalidArgumentException(sprintf('No such username %s', $username));
    }
  }

}