<?php

namespace DPD\Form;

use DPD\Form;
use Symfony\Component\Validator\Constraints as Assert;

class ParcelLabel extends Form {
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
	 * @Assert\NotBlank
	 * @Assert\Length(max = 200)
	 */
	protected $parcels;

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

    /**
     * Set parcel_number
     *
     * @param mixed $parcel_numbers Array or string
     *
     * @return $this
     */
    public function setParcels($parcels)
    {

        if (is_array($parcels)) {
            $parcels = implode('|', $parcels);
        }

        $this->parcels = $parcels;

        return $this;
    }
}