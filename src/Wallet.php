<?php

/**
 * Wallet
 * 
 * Facade interface to hide submodule complexity. 
 * Make us easy to communicate with wallet module
 * 
 * @package Wallet
 * @author Oriza
 */

namespace Gemblue\TinyWallet;

use Gemblue\TinyWallet\Repository\Log;
use Gemblue\TinyWallet\Repository\Ledger;
use Gemblue\TinyWallet\Repository\Transaction;
use Gemblue\TinyWallet\Repository\Repository;

class Wallet {
    
    /** Props */
    public $log;
    public $ledger;
    public $transaction;
    public $credit = ['TOPUP', 'HOLD', 'PAYMENT', 'INCOME'];
    public $debit = ['WITHDRAWAL', 'FEE'];
    
    /**
     * Construct.
     */
    public function __construct(array $config) {
        
        // Connection
        $connection = mysqli_connect($config['host'], $config['username'], $config['password'], $config['database']);
        
        // Setup submodules.
        $this->log = new Log($connection, $config['subjectTable']);
        $this->ledger = new Ledger($connection, $config['subjectTable']);
        $this->transaction = new Transaction($connection, $config['subjectTable']);
    }

    /**
     * Get user latest balance
     */
    public function getBalance(int $subjectId) : int {
        return $this->ledger->getSummary($subjectId);
    }

    /**
     * To record transaction.
     */
    public function record(array $args) : bool {
        
        /** Save transaction. */
        $id = $this->transaction->save([
            'subject_id' => $args['subject_id'],
            'type' => $args['type'],
            'currency' => $args['currency'],
            'amount' => $args['amount'],
            'code' => $args['code'],
            'metadata' => $args['metadata']
        ]);
        
        /** Save log. */
        $this->log->save([
            'transaction_id' => $id,
            'status' => $args['status'],
        ]);
        
        return true;    
    }

    /**
     * To Sync Ledgers and update wallet balance.
     */
    public function syncronize() : bool {
        
        // Get confirmed transaction log.
        $logs = $this->log->getConfirmedLogs();
        
        // Insert into ledger.
        foreach ($logs as $log) {

            $type = $log['type'];
            
            if (in_array($log['type'], $this->credit)) {
                $entry = 'CREDIT';
                $amount = $log['amount'];
            } else {
                $entry = 'DEBIT';
                $amount = '-' . $log['amount'];
            }

            $this->ledger->save([
                'subject_id' => $log['subject_id'],
                'transaction_id' => $log['transaction_id'],
                'amount' => $amount,
                'entry' => $entry
            ]);            
        }
        
        return true;
    }
}