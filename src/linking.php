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
