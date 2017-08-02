<?php

namespace DPD\Form;

use DPD\Form;
use Symfony\Component\Validator\Constraints as Assert;

class ParcelTransferData extends Form {
    /**
     * client’s weblabel username
     *
     * @Assert\NotBlank
     * @Assert\Length(max = 20)
     */
    protected $username;

    /**
     * client’s weblabel password
     *
     * @Assert\NotBlank
     * @Assert\Length(max = 20)
     */
    protected $password;

    /**
     * Set username
     *
     * @param mixed $username
     * @return $this
     */
    public function setUsername($username) {
        $this->username = $username;

        return $this;
    }

    /**
     * Set password
     *
     * @param mixed $password
     * @return $this
     */
    public function setPassword($password) {
        $this->password = $password;

        return $this;
    }
}