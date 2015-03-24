<?php

namespace Eccube\Repository;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Eccube\Entity\Customer;

/**
 * CustomerRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class CustomerRepository extends EntityRepository implements UserProviderInterface
{
    public $app;

    public function setApplication($app)
    {
        $this->app = $app;
    }

    public function newCustomer()
    {
        $customer = new \Eccube\Entity\Customer();

        $customer->setCreateDate(new \DateTime())
            ->setUpdateDate(new \DateTime())
            ->setPoint(0)
            ->setStatus(1)
            ->setDelFlg(0);

        return $customer;
    }

    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param string $username The username
     *
     * @return UserInterface
     *
     * @see UsernameNotFoundException
     *
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($username)
    {
        $query = $this->createQueryBuilder('c')
            ->where('c.email = :email OR c.email_mobile = :email_mobile')
            ->andWhere('c.del_flg = 0')
            ->setParameter('email', $username)
            ->setParameter('email_mobile', $username)
            ->getQuery();
        $customer = $query->getOneOrNullResult();
        if (!$customer) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }

        return $customer;
    }

    /**
     * Refreshes the user for the account interface.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     *
     * @param UserInterface $user
     *
     * @return UserInterface
     *
     * @throws UnsupportedUserException if the account is not supported
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof Customer) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    /**
     * Whether this provider supports the given user class.
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass($class)
    {
        return $class === 'Eccube\Entity\Customer';
    }

}