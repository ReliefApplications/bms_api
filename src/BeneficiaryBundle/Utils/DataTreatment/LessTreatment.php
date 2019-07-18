<?php


namespace BeneficiaryBundle\Utils\DataTreatment;

use ProjectBundle\Entity\Project;

class LessTreatment extends AbstractTreatment
{
    /**
     * Treat the typo issues
     * The frontend returns:
     * {
     *  errors:
     *     [
     *         {
     *             old: [],
     *             new: [],
     *             id_tmp_cache: int,
     *         }
     *     ]
     * }
     * @param Project $project
     * @param array $householdsArray
     * @param string $email
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function treat(Project $project, array &$householdsArray, string $email)
    {
        foreach ($householdsArray as $householdArray) {
            // Save to update the new household with its removed beneficiary
            $this->updateInCache($householdArray['id_tmp_cache'], $householdArray['new'], $email);
        }
        
        $toUpdate = $this->getFromCache('to_update', $email);
        if (! $toUpdate) {
            $toUpdate = [];
        }
        $toCreate = $this->getFromCache('to_create', $email);
        if (! $toCreate) {
            $toCreate = [];
        }

        // to preserve values with the same key
        return array_unique(array_merge($toUpdate, $toCreate), SORT_REGULAR);
    }
}
