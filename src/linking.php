<?php

/**
 * This simulates a PlayFab <-> PlayReal linking workflow
 *
 * Created by PhpStorm.
 * User: yann
 * Date: 11/10/2016
 * Time: 08:09
 */

/**
 * Class Database
 * An abstract class to simulate a super simple database, with some search and linking capabilities
 */
class Database
{
    protected $db = [];     // Database

    protected function SearchDB($key, $val)
    {
        foreach ($this->db as $id => $record) {
            if ($record[$key] == $val) {
                return $record;
            }
        }
        return false;
    }

    public function GetRecord($id)
    {
        if (isset($this->db[$id])) {
            return $this->db[$id];
        } else {
            return false;
        }
    }

    public function Login($id)
    {
        return $this->GetRecord($id);
    }


    public function GetLinkedRecord($link)
    {
        return $this->SearchDB('link', $link);
    }

    public function LinkRecord($id, $link)
    {
        if (isset($this->db[$id])) {
            $this->db[$id]['link'] = $link;
            return $this->db[$id];
        } else {
            return false;
        }
    }

    public function UnlinkRecord($link)
    {
        $record = $this->SearchDB('link', $link);
        if ($record) {
            $this->db[$record['id']]['link'] = null;
            return $this->db[$record['id']];
        } else {
            return false;
        }
    }
}

/**
 * Class PlayReal
 * The PlayReal database.
 */
class PlayReal extends Database
{

    public function AddRecord($id, $login, $link = null)
    {
        $this->db[$id] = array('id' => $id, 'login' => $login, 'link' => $link);
        return $this->db[$id];
    }

    public function GetLinkedUser($link)
    {
        return parent::GetLinkedRecord($link);
    }

    public function LinkUser($id, $link)
    {
        return parent::LinkRecord($id, $link);
    }

    public function UnlinkUser($link)
    {
        return parent::UnlinkRecord($link);
    }
}

/**
 * Class PlayFab
 * The PlayFab database. It adds the concept of "content" which is user data tied to a record.
 */
class PlayFab extends Database
{

    public function AddRecord($id, $device_id, $link = null, $content = null)
    {
        $this->db[$id] = array('id' => $id, 'device_id' => $device_id, 'link' => $link, 'content' => $content);
    }

    public function LoginWithDeviceId($device_id)
    {
        return $this->SearchDB('device_id', $device_id);
    }

    public function LoginWithCustomID($link)
    {
        return $this->SearchDB('link', $link);
    }

    public function LinkCustomId($id, $link)
    {
        return parent::LinkRecord($id, $link);
    }

    public function UnlinkCustomId($link)
    {
        return parent::UnlinkRecord($link);
    }

    public function SetContent($id, $content)
    {
        if (isset($this->db[$id])) {
            $this->db[$id]['content'] = $content;
            return true;
        } else {
            return false;
        }
    }

    public function GetContent($id)
    {
        if (isset($this->db[$id])) {
            return $this->db[$id]['content'];
        } else {
            return false;
        }
    }
}


// ---------------------------------------------------------------------------------------------------------------------
/**
 * The linking workflow.
 * It takes two login parameters as input: the id of the user's device and the user's PlayReal ID (which can be null).
 * @param $playreal PlayReal The PlayReal database
 * @param $playfab PlayFab The PlayFab database
 * @param $device_id string User's device id, which is always tied to a PlayFab account
 * @param $playreal_user_id integer PlayReal user's id. Can be null if we don't have any.
 * @param $content_preference string either 'local' or 'server'. This indicates which side we prefer in case of conflict. Default should be 'server'.
 * @result Array the two accounts and the active content
 */
function RunWorkflowV1($playreal, $playfab, $device_id, $playreal_user_id, $content_preference)
{
    $playfab_user = $original_playfab_user = $playfab->LoginWithDeviceId($device_id);
    $playreal_user = $playreal->Login($playreal_user_id);
    $content = $playfab->GetContent($playfab_user['id']); // This represents the current local content
    if ($playfab_user && $playreal_user) {
        //-- If PlayFab and PlayReal login success
        $linked_playreal_user = $playreal->GetLinkedUser($playfab_user['id']);
        if (empty($linked_playreal_user)) { // If partner id is empty
            //-- So there is no linked PlayReal account to our PlayFab account from the PlayReal perspective
            // Let's check if PlayFab finds something on its side
            $playfab_user = $playfab->LoginWithCustomId($playreal_user_id);
            if ($playfab_user) {
                //-- If login success (it means there was already a PlayFab account linked to this PayReal id)
                // Just to be sure, check if it's really another one
                if ($playfab_user['id'] == $original_playfab_user['id']) {
                    //-- So it was in fact the same one, the link was just missing on the PlayReal side. Correct it
                    $playreal_user = $playreal->LinkUser($playreal_user['id'], $playfab_user['id']);    // Link and get a refresh of the local account info
                } else {
                    //-- We are dealing with two different PlayFab accounts - so we have to choose
                    if ($content_preference == 'local') {
                        //-- Player chooses local content
                        $playfab->UnlinkCustomId($playreal_user['id']); // ??? Why are we doing this ??? In case the PlayFab data was linked to another account ???
                        /* !!! do this too: !!! */
                        $playreal->UnlinkUser($playfab_user['id']); // Unlink

                        $playfab_user = $original_playfab_user; // Switch back to our original PlayFab account
                        //$playfab->SetContent($playfab_user['id'], $content);
                        $playfab_user = $playfab->LinkCustomId($playfab_user['id'], $playreal_user['id']); // Link and get a refresh of the local account info
                        /* !!! do this too: !!! */
                        $playreal_user = $playreal->LinkUser($playreal_user['id'], $playfab_user['id']);    // Link and get a refresh of the local account info
                    } else {
                        //-- Player chooses server content
                        // Clear local content
                        $playfab->SetContent($original_playfab_user['id'], '');
                        // Autologin with PlayReal using store access token (not implemented in this simulator)
                        // Login in PlayFab again
                        $playfab_user = $playfab->LoginWithCustomID($playreal_user['id']);
                    }
                }
            } else {
                //-- No linked PlayFab account was found for this PlayReal user. Grab a PlayFab account and link it to this PlayReal account
                $playfab_user = $original_playfab_user; // Switch back to our original PlayFab account
                $playfab_user = $playfab->LinkCustomId($playfab_user['id'], $playreal_user['id']); // Attach the PlayReal user to this PlayFab account. And get a refresh of the local account info
                /* !!! do this too: !!! */
                $playreal_user = $playreal->LinkUser($playreal_user['id'], $playfab_user['id']); // Link and get a refresh of the local account info
            }
        } else { // $linked_playreal_user is not empty:
            // !!! New code: the PlayReal account should win if it is already link to a PlayFab account. Otherwise it should lose !!!
            if ($linked_playreal_user['link'] != $playreal_user['link']) {
                // The two linked PlayFab accounts are different
                if ($playreal_user['link']) {
                    $playfab_user = $playfab->LoginWithCustomID($playreal_user['id']);  // PlayReal account wins -> switch to its PlayFab account
                } else {
                    $playreal_user = $linked_playreal_user; // PlayFab account wins -> change PlayReal account
                }
            } else {
                //-- We're already good.
                // Just make sure that the PlayFab account links to the PlayReal acount, just in case
                if (empty($playfab_user['link'])) {
                    $playfab_user = $playfab->LinkCustomId($playfab_user['id'], $playreal_user['id']); // Attach the PlayReal user to this PlayFab account. And get a refresh of the local account info
                }
            }
        }
        // Retrieve all server progress to continue play with that progress
        $content = $playfab_user['content'];
    } else {
        //-- !!! New code: If PlayFab or PlayReal login failure
        if ($playfab_user) {
            if ($playfab_user['link']) {
                // We have a PlayFab account with a linked PlayReal user -> use it
                $playreal_user = $playreal->Login($playfab_user['link']);
            } else {
                // Create or Login in a PlayReal account -> launch the PlayReal SSO
                // (not implemented in the context of this simulator. We just create a fixed PlayReal account)
                $playreal_user = $playreal->AddRecord(6, 'John', null);
                // Then run the workflow again
                return RunWorkflow($playreal, $playfab, $device_id, $playreal_user['id'], $content_preference);
            }
        }
    }
    return ['playreal_user' => $playreal_user,
        'playfab_user' => $playfab_user,
        'content' => $content,
    ];
}

// ---------------------------------------------------------------------------------------------------------------------
/**
 * The linking workflow.
 * It takes two login parameters as input: the id of the user's device and the user's PlayReal ID (which can be null).
 * @param $playreal PlayReal The PlayReal database
 * @param $playfab PlayFab The PlayFab database
 * @param $device_id string User's device id, which is always tied to a PlayFab account
 * @param $playreal_user_id integer PlayReal user's id. Can be null if we don't have any.
 * @param $content_preference string either 'local' or 'server'. This indicates which side we prefer in case of conflict. Default should be 'server'.
 * @result Array the two accounts and the active content
 */
function RunWorkflow($playreal, $playfab, $device_id, $playreal_user_id, $content_preference)
{
    $playfab_user = $current_playfab_user = $playfab->LoginWithDeviceId($device_id);
    $playreal_user = $current_playreal_user = $playreal->Login($playreal_user_id);
    if ($playfab_user && $playreal_user) {
        $other_playreal_user_id = $playfab_user['link'];   // The linked PlayReal account from the perspective of the current PlayFab account
        $other_playfab_user_id = $playreal_user['link'];   // The linked PlayFab account from the perspective of the current PlayReal account

        if ($other_playreal_user_id == null && $other_playfab_user_id == null) {
            //-- Case #1: none is linked: we need to marry them
            $playfab_user = $playfab->LinkCustomId($playfab_user['id'], $playreal_user['id']);
            $playreal_user = $playreal->LinkUser($playreal_user['id'], $playfab_user['id']);
        } elseif ($other_playreal_user_id == null && $other_playfab_user_id == $playfab_user['id']) {
            //-- Case #2: Only the PlayReal account is pointing to the PlayFab account: fix the PlayFab account by adding the PlayReal link
            $playfab->UnlinkCustomId($playreal_user['id']); // Make sure the PlayReal account is not used by any other PlayFab account
            $playfab_user = $playfab->LinkCustomId($playfab_user['id'], $playreal_user['id']);
        } elseif ($other_playreal_user_id == null && $other_playfab_user_id != $playfab_user['id']) {
            //-- Case #3: The PlayReal account is pointing to ANOTHER PlayFab account: ask the user which PlayFab data she wants to keep
            if ($content_preference == 'local') {
                //-- Player chooses "local content", i.e. the current PlayFab account
                $playfab->UnlinkCustomId($playreal_user['id']); // Make sure the PlayReal account is not used by any other PlayFab account
                $playreal->UnlinkUser($playfab_user['id']);     // Make sure the PlayFab account is not used by any other PlayReal account

                $playfab_user = $playfab->LinkCustomId($playfab_user['id'], $playreal_user['id']); // Link the two accounts together
                $playreal_user = $playreal->LinkUser($playreal_user['id'], $playfab_user['id']);   // Link the two accounts together
            } else {
                //-- Player chooses "server content", i.e. the PlayFab content that was attached to the current PlayReal account
                // Clear local content so it won't be usable
                $playfab->SetContent($current_playfab_user['id'], '');
                // Current PlayFab account becomes the one of the current PlayReal account
                $playfab_user = $playfab->Login($other_playfab_user_id);
                // Make sure it points back to the PlayReal account, just in case
                if ($playfab_user['link'] != $playreal_user['id']) {
                    $playfab_user = $playfab->LinkCustomId($playfab_user['id'], $playreal_user['id']); // Link the the right PlayReal account
                }
            }
        } elseif ($other_playreal_user_id == $playreal_user['id'] && $other_playfab_user_id == null) {
            //-- Case #4: PlayReal link is missing: fix the PlayReal account by adding the PlayFab link
            $playreal->UnlinkUser($playfab_user['id']);     // Make sure the PlayFab account is not used by any other PlayReal account
            $playreal_user = $playreal->LinkUser($playreal_user['id'], $playfab_user['id']);
        } elseif ($other_playreal_user_id == $playreal_user['id'] && $other_playfab_user_id == $playfab_user['id']) {
            //-- Case #5: Both point to each other: nothing to do, we're GOOD
        } elseif ($other_playreal_user_id == $playreal_user['id'] && $other_playfab_user_id != $playfab_user['id']) {
            //-- Case #6: Inconsistency: the PlayFab account points to the current PlayReal account but the PlayReal account points to ANOTHER PlayFab account: We should switch to that PlayFab account ("PlayReal wins") and correct the links
            $playfab->UnlinkCustomId($playreal_user['id']); // Make sure the PlayReal account is not used by any other PlayFab account
            $playfab_user = $playfab->Login($other_playfab_user_id);
            $playfab_user = $playfab->LinkCustomId($playfab_user['id'], $playreal_user['id']); // Link the two accounts together
        } elseif ($other_playreal_user_id != $playreal_user['id'] && $other_playfab_user_id == null) {
            //-- Case #7: orphan PlayReal account: the PlayFab account should win
            $playreal->UnlinkUser($playfab_user['id']);     // Make sure the PlayFab account is not used by any other PlayReal account
            $playreal_user = $playreal->LinkUser($other_playreal_user_id, $playfab_user['id']);   // Link the two accounts together
        } elseif ($other_playreal_user_id != $playreal_user['id'] && $other_playfab_user_id == $playfab_user['id']) {
            //-- Case #8: Inconsistency:
            $playfab->UnlinkCustomId($other_playreal_user_id); // Make sure the other PlayReal account is not used by any other PlayFab account
            $playfab_user = $playfab->LinkCustomId($playfab_user['id'], $playreal_user['id']);
        } elseif ($other_playreal_user_id != $playreal_user['id'] && $other_playfab_user_id != $playfab_user['id']) {
            //-- Case #9: each is linked to another: the PlayReal account should win
            $playfab_user = $playfab->Login($other_playfab_user_id);
            $playfab->UnlinkCustomId($playreal_user['id']); // Make sure the current PlayReal account is not used by any other PlayFab account
            $playfab_user = $playfab->LinkCustomId($playfab_user['id'], $playreal_user['id']);  // Make sure it is linked to the PlayReal account
        }
    } elseif ($playfab_user && $playreal_user == null) {
        //-- No PlayReal user => find one or use the SSO
        if ($playfab_user['link']) {
            // We have a PlayFab account with a linked PlayReal user -> use it
            $playreal_user = $playreal->Login($playfab_user['link']);
        } else {
            // Create or Login in a PlayReal account -> launch the PlayReal SSO
            // (not implemented in the context of this simulator. We just create a fixed PlayReal account)
            $playreal_user = $playreal->AddRecord(6, 'John', null);
            // Then run the workflow again
            return RunWorkflow($playreal, $playfab, $device_id, $playreal_user['id'], $content_preference);
        }
    } else {
        throw new Exception('There should always be a PlayFab account');
    }
    return ['playreal_user' => $playreal_user,
        'playfab_user' => $playfab_user,
        'content' => $playfab_user['content'],
    ];
}
