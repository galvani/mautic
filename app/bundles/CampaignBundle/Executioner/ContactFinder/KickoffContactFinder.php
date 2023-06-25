<?php

namespace Mautic\CampaignBundle\Executioner\ContactFinder;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Entity\CampaignRepository;
use Mautic\CampaignBundle\Executioner\ContactFinder\Limiter\ContactLimiter;
use Mautic\CampaignBundle\Executioner\Exception\NoContactsFoundException;
use Mautic\LeadBundle\Entity\LeadRepository;
use Psr\Log\LoggerInterface;

class KickoffContactFinder
{
    /**
     * @var LeadRepository
     */
    private $leadRepository;

    /**
     * @var CampaignRepository
     */
    private $campaignRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * KickoffContactFinder constructor.
     */
    public function __construct(LeadRepository $leadRepository, CampaignRepository $campaignRepository, LoggerInterface $logger)
    {
        $this->leadRepository     = $leadRepository;
        $this->campaignRepository = $campaignRepository;
        $this->logger             = $logger;
    }

    /**
     * @param int $campaignId
     *
     * @return ArrayCollection
     *
     * @throws NoContactsFoundException
     */
    public function getContacts($campaignId, ContactLimiter $limiter)
    {
        // Get list of all campaign leads; start is always zero in practice because of $pendingOnly
        $campaignContacts = $this->campaignRepository->getPendingContactIds($campaignId, $limiter);
        printf(" ## KCF getContacts getPending start %s\n", memuse());

        if (empty($campaignContacts)) {
            // No new contacts found in the campaign

            throw new NoContactsFoundException();
        }

        //$this->logger->debug('CAMPAIGN: Processing the following contacts: '.implode(', ', $campaignContacts));

        // Fetch entity objects for the found contacts
        $this->clear();
        printf(" ## KCF getContacts after clear %s\n", memuse());

//        for ($i=0;$i<10;$i++) {
//            $contacts = $this->leadRepository->getContactCollection($campaignContacts);
//            unset($contacts);
//            printf("------- test run %s\n", memuse());
//
//        }
        $contacts = $this->leadRepository->getContactCollection($campaignContacts);

        //die();
        printf(" ## KCF getContacts after fetch %s\n", memuse());
//        unset($campaignContacts);
//        printf(" ## KCF getContacts after unset %s\n", memuse());
//        gc_collect_cycles();
//        printf(" ## KCF getContacts after gc_collect %s\n", memuse());
        if (!count($contacts)) {
            // Just a precaution in case non-existent contacts are lingering in the campaign leads table
            $this->logger->debug('CAMPAIGN: No contact entities found.');

            throw new NoContactsFoundException();
        }

        return $contacts;
    }

    /**
     * @param int $campaignId
     *
     * @return int
     */
    public function getContactCount($campaignId, array $eventIds, ContactLimiter $limiter)
    {
        $countResult = $this->campaignRepository->getCountsForPendingContacts($campaignId, $eventIds, $limiter);

        return $countResult->getCount();
    }

    /**
     * Clear Lead entities from memory.
     */
    public function clear()
    {
        $this->leadRepository->clear();
    }
}
