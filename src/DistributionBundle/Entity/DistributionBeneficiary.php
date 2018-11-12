<?php

namespace DistributionBundle\Entity;

use BeneficiaryBundle\Entity\Beneficiary;
use TransactionBundle\Entity\Transaction;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;

/**
 * DistributionBeneficiary
 *
 * @ORM\Table(name="distribution_beneficiary")
 * @ORM\Entity(repositoryClass="DistributionBundle\Repository\DistributionBeneficiaryRepository")
 */
class DistributionBeneficiary
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"FullDistributionBeneficiary", "FullDistribution", "Transaction"})
     */
    private $id;

    /**
     * @var DistributionData
     *
     * @ORM\ManyToOne(targetEntity="DistributionBundle\Entity\DistributionData", inversedBy="distributionBeneficiaries")
     * @Groups({"FullDistributionBeneficiary"})
     */
    private $distributionData;

    /**
     * @var Beneficiary
     *
     * @ORM\ManyToOne(targetEntity="BeneficiaryBundle\Entity\Beneficiary", inversedBy="distributionBeneficiary")
     * @Groups({"FullDistributionBeneficiary", "FullDistribution", "Transaction"})
     */
    private $beneficiary;
    
    /**
     * @var Transaction
     *
     * @ORM\OneToMany(targetEntity="TransactionBundle\Entity\Transaction", mappedBy="distributionBeneficiary")
     * @Groups({"FullHousehold", "SmallHousehold", "FullDistribution", "Transaction"})
     */
    private $transactions;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set distributionData.
     *
     * @param \DistributionBundle\Entity\DistributionData|null $distributionData
     *
     * @return DistributionBeneficiary
     */
    public function setDistributionData(\DistributionBundle\Entity\DistributionData $distributionData = null)
    {
        $this->distributionData = $distributionData;

        return $this;
    }

    /**
     * Get distributionData.
     *
     * @return \DistributionBundle\Entity\DistributionData|null
     */
    public function getDistributionData()
    {
        return $this->distributionData;
    }

    /**
     * Set beneficiary.
     *
     * @param \BeneficiaryBundle\Entity\Beneficiary|null $beneficiary
     *
     * @return DistributionBeneficiary
     */
    public function setBeneficiary(\BeneficiaryBundle\Entity\Beneficiary $beneficiary = null)
    {
        $this->beneficiary = $beneficiary;

        return $this;
    }

    /**
     * Get beneficiary.
     *
     * @return \BeneficiaryBundle\Entity\Beneficiary|null
     */
    public function getBeneficiary()
    {
        return $this->beneficiary;
    }
 
    /**
     * Get the value of Transaction 
     * 
     * @return Transaction
     */
    public function getTransactions()
    {
        return $this->transactions;
    }
 
    /** 
     * Add a Transaction 
     * 
     * @param Transaction transaction
     * 
     * @return self
     */
    public function addTransaction(Transaction $transaction)
    {
        $this->transactions[] = $transaction;
 
        return $this;
    }
    
    /**
     * Remove a Transaction
     * @param  Transaction $transaction
     * @return self                  
     */
    public function removeTransaction(Transaction $transaction)
    {
        $this->transactions->removeElement($transaction);
        return $this;
    }
    
    /**
     * Set transactions
     *
     * @param $collection
     *
     * @return self
     */
    public function setPhones(\Doctrine\Common\Collections\Collection $collection = null)
    {
        $this->transactions = $collection;

        return $this;
    }
 
}
