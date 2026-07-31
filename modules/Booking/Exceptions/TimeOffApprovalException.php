<?php

namespace Modules\Booking\Exceptions;

/**
 * Internal control-flow exception for PractitionerTimeOff::approve():
 * thrown inside the approval transaction to roll back the allocation
 * deduction when the status update fails to persist. Deliberately NOT a
 * RuntimeException subclass catch — PDO/Query exceptions extend
 * RuntimeException and must propagate, not be mistaken for a balance
 * failure.
 */
class TimeOffApprovalException extends \Exception {}
