<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Tester\Exception\PendingException;
use PHPUnit_Framework_Assert as Assert;

require_once "src/linking.php";

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
    protected $playreal = null;
    protected $playfab = null;
    protected $device = '';                     // The device ID we are using to connect to PlayFab at the start of the test
    protected $playreal_id = null;              // The PlayReal account we are connected to at the start of the test
    protected $content_preference = 'server';   // The preference in case we have to choose between two contents: 'server' or 'local'
    protected $original_content = null;         // Content that was in the first PlayFab account we were connected to

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
        $this->playreal = new PlayReal();
        $this->playfab = new PlayFab();
    }

    /**
     * @Given I have a linked PlayFab account
     */
    public function iHaveALinkedPlayfabAccount()
    {
        $this->device = 'device_AAAA';
        $this->playfab->AddRecord(100000004, $this->device, 4, 'content 100000004');
        $this->original_content = $this->playfab->GetContent(100000004);

        $this->playreal_id = 4;
        $this->playreal->AddRecord($this->playreal_id, 'Yann', 100000004);
    }

    /**
     * @Given I have a non linked PlayFab account
     */
    public function iHaveANonLinkedPlayfabAccount()
    {
        $this->device = 'device_BBBB';
        $this->playfab->AddRecord(100000011, $this->device, null, 'content 100000011');
        $this->original_content = $this->playfab->GetContent(100000011);
    }

    /**
     * @Given I have a non linked PlayReal account
     */
    public function iHaveANonLinkedPlayrealAccount()
    {
        $this->playreal_id = 3;
        $this->playreal->AddRecord($this->playreal_id, 'Eric', null);
    }

    /**
     * @Given /^I have a PlayReal account linked to another PlayFab account$/
     */
    public function iHaveAPlayRealAccountLinkedToAnotherPlayFabAccount()
    {
        $this->playreal_id = 5;
        $this->playreal->AddRecord($this->playreal_id, 'Brice', '100005555');
        $this->playfab->AddRecord(100005555, 'device_CCCC', $this->playreal_id, 'content 100005555');
        $this->original_content = $this->playfab->GetContent(100005555);
    }

    /**
     * @Given /^I have no PlayReal account$/
     */
    public function iHaveNoPlayRealAccount()
    {
        $this->playreal_id = null;
    }

    /**
     * @When I run the workflow
     */
    public function iRunTheWorkflow()
    {
        $this->results = RunWorkflow($this->playreal, $this->playfab, $this->device, $this->playreal_id, $this->content_preference);
    }

    /**
     * @Then I should get two accounts
     */
    public function iShouldGetTwoAccounts()
    {
        Assert::assertTrue(isset($this->results['playreal_user']['id']), 'No playreal_user');
        Assert::assertTrue(isset($this->results['playfab_user']['id']), 'No playfab_user');
        //print_r($this->results);
    }

    /**
     * @Then My accounts should be linked together
     */
    public function myAccountsShouldBeLinkedTogether()
    {
        Assert::assertEquals($this->results['playreal_user']['link'], $this->results['playfab_user']['id'], 'playreal_user NOT linked to playfab_user');
        Assert::assertEquals($this->results['playfab_user']['link'], $this->results['playreal_user']['id'], 'playfab_user NOT linked to playreal_user');
    }

    /**
     * @Then My content should be the one from my PlayFab account
     */
    public
    function myContentShouldBeTheOneFromMyPlayfabAccount()
    {
        Assert::assertEquals($this->results['content'], $this->playfab->GetContent($this->results['playfab_user']['id']), 'The content is not the server one');
    }

    /**
     * @Given /^I choose to discard the current content$/
     */
    public function iChooseToDiscardTheCurrentContent()
    {
        $this->content_preference = 'server';
    }

    /**
     * @Given /^I choose to keep the current content$/
     */
    public function iChooseToKeepTheCurrentContent()
    {
        $this->content_preference = 'local';
    }

    /**
     * @Given /^I should be using the other PlayFab account$/
     */
    public function iShouldBeUsingTheOtherPlayFabAccount()
    {
        Assert::assertEquals(100005555, $this->results['playfab_user']['id']);
    }

    /**
     * @Given /^My content should be the one from my other PlayFab account$/
     */
    public function myContentShouldBeTheOneFromMyOtherPlayFabAccount()
    {
        Assert::assertEquals($this->results['content'], $this->playfab->GetContent(100005555), 'The content is not the one from my other PlayFab account');
    }

    /**
     * @Given /^The PlayReal account should be the one linked to my other PlayFab account$/
     */
    public function thePlayRealAccountShouldBeTheOneLinkedToMyOtherPlayFabAccount()
    {
        Assert::assertEquals(5, $this->results['playreal_user']['id']);
    }

    /**
     * @Given /^I should be using the original PlayFab account$/
     */
    public function iShouldBeUsingTheOriginalPlayFabAccount()
    {
        $original_playfab_account = $this->playfab->LoginWithDeviceId($this->device);
        Assert::assertEquals($original_playfab_account['id'], $this->results['playfab_user']['id']);
    }

    /**
     * @Given /^My content should be the one from the original PlayFab account$/
     */
    public function myContentShouldBeTheOneFromTheOriginalPlayFabAccount()
    {
        $original_playfab_account = $this->playfab->LoginWithDeviceId($this->device);
        Assert::assertEquals($original_playfab_account['content'], $this->results['content']);
    }

    /**
     * @Given /^The PlayReal account should be the one linked to the original PlayFab account$/
     */
    public function thePlayRealAccountShouldBeTheOneLinkedToTheOriginalPlayFabAccount()
    {
        Assert::assertEquals(4, $this->results['playreal_user']['id']);
    }

    /**
     * @Given /^the content of the original PlayFab account should be cleared$/
     */
    public function theContentOfTheOriginalPlayFabAccountShouldBeCleared()
    {
        Assert::assertEquals('', $this->playfab->GetContent(100000011));
    }

    /**
     * @Given /^I have a partially linked PlayFab account$/
     */
    public function iHaveAPartiallyLinkedPlayFabAccount()
    {
        $this->device = 'device_AAAA';
        $this->playfab->AddRecord(100000004, $this->device, 4, 'content 100000004');
        $this->original_content = $this->playfab->GetContent(100000004);

        $this->playreal_id = 4;
        $this->playreal->AddRecord($this->playreal_id, 'Yann', null);
    }

    /**
     * @Given /^I have a partially linked PlayReal account$/
     */
    public function iHaveAPartiallyLinkedPlayRealAccount()
    {
        $this->device = 'device_AAAA';
        $this->playfab->AddRecord(100000004, $this->device, null, 'content 100000004');
        $this->original_content = $this->playfab->GetContent(100000004);

        $this->playreal_id = 4;
        $this->playreal->AddRecord($this->playreal_id, 'Yann', 100000004);
    }

    /**
     * @Given /^I have a linked PlayFab account to a badly linked PlayReal account$/
     */
    public function iHaveALinkedPlayFabAccountToABadlyLinkedPlayRealAccount()
    {
        $this->device = 'device_AAAA';
        $this->playfab->AddRecord(100000004, $this->device, 4, 'content 100000004');
        $this->original_content = $this->playfab->GetContent(100000004);

        $this->playfab->AddRecord(100005555, 'device_CCCC', $this->playreal_id, 'content 100005555');

        $this->playreal_id = 4;
        $this->playreal->AddRecord($this->playreal_id, 'Yann', 100005555);
    }

    /**
     * @Given /^I have a linked PlayReal account to a badly linked PlayFab account$/
     */
    public function iHaveALinkedPlayRealAccountToABadlyLinkedPlayFabAccount()
    {
        $this->device = 'device_AAAA';
        $this->playfab->AddRecord(100000004, $this->device, 3, 'content 100000004');
        $this->original_content = $this->playfab->GetContent(100000004);

        $this->playreal_id = 4;
        $this->playreal->AddRecord($this->playreal_id, 'Yann', 100000004);

        $this->playreal->AddRecord(3, 'Eric', null);
    }


}
