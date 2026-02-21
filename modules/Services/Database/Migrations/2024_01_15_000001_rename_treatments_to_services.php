<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename all treatment-related tables and columns to service-related names.
     */
    public function up(): void
    {
        // Step 1: Drop all foreign key constraints that reference the tables we're renaming
        $this->dropForeignKeys();

        // Step 2: Rename columns in dependent tables BEFORE renaming the main tables
        $this->renameColumns();

        // Step 3: Rename the main tables
        Schema::rename('treatment_categories', 'service_categories');
        Schema::rename('treatments', 'services');
        Schema::rename('treatment_branch_pricing', 'service_branch_pricing');

        if (Schema::hasTable('treatment_equipment_requirements')) {
            Schema::rename('treatment_equipment_requirements', 'service_equipment_requirements');
        }

        // Step 4: Recreate foreign key constraints with new names
        $this->recreateForeignKeys();

        // Step 5: Rename indexes (PostgreSQL syntax)
        $this->renameIndexes();
    }

    public function down(): void
    {
        // Step 1: Drop all foreign key constraints
        $this->dropForeignKeysReverse();

        // Step 2: Rename columns back
        $this->renameColumnsReverse();

        // Step 3: Rename tables back
        Schema::rename('service_categories', 'treatment_categories');
        Schema::rename('services', 'treatments');
        Schema::rename('service_branch_pricing', 'treatment_branch_pricing');

        if (Schema::hasTable('service_equipment_requirements')) {
            Schema::rename('service_equipment_requirements', 'treatment_equipment_requirements');
        }

        // Step 4: Recreate original foreign key constraints
        $this->recreateForeignKeysReverse();
    }

    private function dropForeignKeys(): void
    {
        // Drop FK on treatments table (references treatment_categories)
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        // Drop FK on treatment_branch_pricing (references treatments)
        Schema::table('treatment_branch_pricing', function (Blueprint $table) {
            $table->dropForeign(['treatment_id']);
        });

        // Drop FK on treatment_categories (self-reference)
        Schema::table('treatment_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        // Drop FK on appointments
        if (Schema::hasColumn('appointments', 'treatment_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropForeign(['treatment_id']);
            });
        }

        // Drop FK on waitlist
        if (Schema::hasTable('waitlist') && Schema::hasColumn('waitlist', 'treatment_id')) {
            Schema::table('waitlist', function (Blueprint $table) {
                $table->dropForeign(['treatment_id']);
            });
        }

        // Drop FK on staff_commissions
        if (Schema::hasTable('staff_commissions')) {
            Schema::table('staff_commissions', function (Blueprint $table) {
                if (Schema::hasColumn('staff_commissions', 'treatment_id')) {
                    $table->dropForeign(['treatment_id']);
                }
                if (Schema::hasColumn('staff_commissions', 'treatment_category_id')) {
                    $table->dropForeign(['treatment_category_id']);
                }
            });
        }

        // Drop FK on loyalty_rules
        if (Schema::hasTable('loyalty_rules')) {
            Schema::table('loyalty_rules', function (Blueprint $table) {
                if (Schema::hasColumn('loyalty_rules', 'treatment_id')) {
                    $table->dropForeign(['treatment_id']);
                }
                if (Schema::hasColumn('loyalty_rules', 'treatment_category_id')) {
                    $table->dropForeign(['treatment_category_id']);
                }
            });
        }

        // Drop FK on package_items
        if (Schema::hasTable('package_items') && Schema::hasColumn('package_items', 'treatment_id')) {
            Schema::table('package_items', function (Blueprint $table) {
                $table->dropForeign(['treatment_id']);
            });
        }

        // Drop FK on package_session_usages
        if (Schema::hasTable('package_session_usages') && Schema::hasColumn('package_session_usages', 'treatment_id')) {
            Schema::table('package_session_usages', function (Blueprint $table) {
                $table->dropForeign(['treatment_id']);
            });
        }

        // Drop FK on treatment_equipment_requirements
        if (Schema::hasTable('treatment_equipment_requirements')) {
            Schema::table('treatment_equipment_requirements', function (Blueprint $table) {
                $table->dropForeign(['treatment_id']);
            });
        }
    }

    private function renameColumns(): void
    {
        // Rename treatment_id to service_id in various tables
        if (Schema::hasColumn('appointments', 'treatment_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->renameColumn('treatment_id', 'service_id');
            });
        }

        if (Schema::hasTable('waitlist') && Schema::hasColumn('waitlist', 'treatment_id')) {
            Schema::table('waitlist', function (Blueprint $table) {
                $table->renameColumn('treatment_id', 'service_id');
            });
        }

        if (Schema::hasTable('staff_commissions')) {
            if (Schema::hasColumn('staff_commissions', 'treatment_id')) {
                Schema::table('staff_commissions', function (Blueprint $table) {
                    $table->renameColumn('treatment_id', 'service_id');
                });
            }
            if (Schema::hasColumn('staff_commissions', 'treatment_category_id')) {
                Schema::table('staff_commissions', function (Blueprint $table) {
                    $table->renameColumn('treatment_category_id', 'service_category_id');
                });
            }
        }

        if (Schema::hasTable('patient_photos') && Schema::hasColumn('patient_photos', 'treatment_id')) {
            Schema::table('patient_photos', function (Blueprint $table) {
                $table->renameColumn('treatment_id', 'service_id');
            });
        }

        if (Schema::hasTable('loyalty_rules')) {
            if (Schema::hasColumn('loyalty_rules', 'treatment_id')) {
                Schema::table('loyalty_rules', function (Blueprint $table) {
                    $table->renameColumn('treatment_id', 'service_id');
                });
            }
            if (Schema::hasColumn('loyalty_rules', 'treatment_category_id')) {
                Schema::table('loyalty_rules', function (Blueprint $table) {
                    $table->renameColumn('treatment_category_id', 'service_category_id');
                });
            }
        }

        if (Schema::hasTable('invoice_lines') && Schema::hasColumn('invoice_lines', 'treatment_id')) {
            Schema::table('invoice_lines', function (Blueprint $table) {
                $table->renameColumn('treatment_id', 'service_id');
            });
        }

        if (Schema::hasTable('package_items') && Schema::hasColumn('package_items', 'treatment_id')) {
            Schema::table('package_items', function (Blueprint $table) {
                $table->renameColumn('treatment_id', 'service_id');
            });
        }

        if (Schema::hasTable('package_session_usages') && Schema::hasColumn('package_session_usages', 'treatment_id')) {
            Schema::table('package_session_usages', function (Blueprint $table) {
                $table->renameColumn('treatment_id', 'service_id');
            });
        }

        // Rename in the branch pricing table (before renaming the table)
        Schema::table('treatment_branch_pricing', function (Blueprint $table) {
            $table->renameColumn('treatment_id', 'service_id');
        });

        // Rename in treatment_equipment_requirements (before renaming the table)
        if (Schema::hasTable('treatment_equipment_requirements')) {
            Schema::table('treatment_equipment_requirements', function (Blueprint $table) {
                $table->renameColumn('treatment_id', 'service_id');
            });
        }

        // Rename tenant_usage column
        if (Schema::hasTable('tenant_usage') && Schema::hasColumn('tenant_usage', 'treatments')) {
            Schema::table('tenant_usage', function (Blueprint $table) {
                $table->renameColumn('treatments', 'services');
            });
        }
    }

    private function recreateForeignKeys(): void
    {
        // FK on services table (references service_categories)
        Schema::table('services', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('service_categories')
                ->onDelete('set null');
        });

        // FK on service_branch_pricing (references services)
        Schema::table('service_branch_pricing', function (Blueprint $table) {
            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->onDelete('cascade');
        });

        // FK on service_categories (self-reference)
        Schema::table('service_categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('service_categories')
                ->onDelete('set null');
        });

        // FK on appointments
        if (Schema::hasColumn('appointments', 'service_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->foreign('service_id')
                    ->references('id')
                    ->on('services')
                    ->onDelete('cascade');
            });
        }

        // FK on waitlist
        if (Schema::hasTable('waitlist') && Schema::hasColumn('waitlist', 'service_id')) {
            Schema::table('waitlist', function (Blueprint $table) {
                $table->foreign('service_id')
                    ->references('id')
                    ->on('services')
                    ->onDelete('cascade');
            });
        }

        // FK on staff_commissions
        if (Schema::hasTable('staff_commissions')) {
            Schema::table('staff_commissions', function (Blueprint $table) {
                if (Schema::hasColumn('staff_commissions', 'service_id')) {
                    $table->foreign('service_id')
                        ->references('id')
                        ->on('services')
                        ->onDelete('set null');
                }
                if (Schema::hasColumn('staff_commissions', 'service_category_id')) {
                    $table->foreign('service_category_id')
                        ->references('id')
                        ->on('service_categories')
                        ->onDelete('set null');
                }
            });
        }

        // FK on loyalty_rules
        if (Schema::hasTable('loyalty_rules')) {
            Schema::table('loyalty_rules', function (Blueprint $table) {
                if (Schema::hasColumn('loyalty_rules', 'service_id')) {
                    $table->foreign('service_id')
                        ->references('id')
                        ->on('services')
                        ->onDelete('set null');
                }
                if (Schema::hasColumn('loyalty_rules', 'service_category_id')) {
                    $table->foreign('service_category_id')
                        ->references('id')
                        ->on('service_categories')
                        ->onDelete('set null');
                }
            });
        }

        // FK on package_items
        if (Schema::hasTable('package_items') && Schema::hasColumn('package_items', 'service_id')) {
            Schema::table('package_items', function (Blueprint $table) {
                $table->foreign('service_id')
                    ->references('id')
                    ->on('services')
                    ->onDelete('cascade');
            });
        }

        // FK on package_session_usages
        if (Schema::hasTable('package_session_usages') && Schema::hasColumn('package_session_usages', 'service_id')) {
            Schema::table('package_session_usages', function (Blueprint $table) {
                $table->foreign('service_id')
                    ->references('id')
                    ->on('services')
                    ->onDelete('cascade');
            });
        }

        // FK on service_equipment_requirements
        if (Schema::hasTable('service_equipment_requirements')) {
            Schema::table('service_equipment_requirements', function (Blueprint $table) {
                $table->foreign('service_id')
                    ->references('id')
                    ->on('services')
                    ->onDelete('cascade');
            });
        }
    }

    private function renameIndexes(): void
    {
        // PostgreSQL allows renaming indexes directly
        // These are the most commonly named indexes that would need renaming
        // Laravel auto-generates index names based on table_column_type pattern

        $indexRenames = [
            // service_categories indexes
            ['treatment_categories_tenant_id_index', 'service_categories_tenant_id_index'],
            ['treatment_categories_parent_id_index', 'service_categories_parent_id_index'],
            ['treatment_categories_tenant_id_is_active_index', 'service_categories_tenant_id_is_active_index'],
            ['treatment_categories_tenant_id_parent_id_sort_order_index', 'service_categories_tenant_id_parent_id_sort_order_index'],

            // services indexes
            ['treatments_tenant_id_index', 'services_tenant_id_index'],
            ['treatments_category_id_index', 'services_category_id_index'],
            ['treatments_consent_template_id_index', 'services_consent_template_id_index'],
            ['treatments_code_index', 'services_code_index'],
            ['treatments_tenant_id_code_unique', 'services_tenant_id_code_unique'],
            ['treatments_tenant_id_is_active_index', 'services_tenant_id_is_active_index'],
            ['treatments_tenant_id_category_id_sort_order_index', 'services_tenant_id_category_id_sort_order_index'],
            ['treatments_tenant_id_is_bookable_online_is_active_index', 'services_tenant_id_is_bookable_online_is_active_index'],

            // service_branch_pricing indexes
            ['treatment_branch_pricing_treatment_id_index', 'service_branch_pricing_service_id_index'],
            ['treatment_branch_pricing_treatment_id_branch_id_unique', 'service_branch_pricing_service_id_branch_id_unique'],
        ];

        foreach ($indexRenames as [$oldName, $newName]) {
            try {
                DB::statement("ALTER INDEX IF EXISTS {$oldName} RENAME TO {$newName}");
            } catch (\Exception $e) {
                // Index might not exist with this exact name, continue
            }
        }
    }

    private function dropForeignKeysReverse(): void
    {
        // Same as dropForeignKeys but with new names
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::table('service_branch_pricing', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        if (Schema::hasColumn('appointments', 'service_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropForeign(['service_id']);
            });
        }

        if (Schema::hasTable('waitlist') && Schema::hasColumn('waitlist', 'service_id')) {
            Schema::table('waitlist', function (Blueprint $table) {
                $table->dropForeign(['service_id']);
            });
        }

        if (Schema::hasTable('staff_commissions')) {
            Schema::table('staff_commissions', function (Blueprint $table) {
                if (Schema::hasColumn('staff_commissions', 'service_id')) {
                    $table->dropForeign(['service_id']);
                }
                if (Schema::hasColumn('staff_commissions', 'service_category_id')) {
                    $table->dropForeign(['service_category_id']);
                }
            });
        }

        if (Schema::hasTable('loyalty_rules')) {
            Schema::table('loyalty_rules', function (Blueprint $table) {
                if (Schema::hasColumn('loyalty_rules', 'service_id')) {
                    $table->dropForeign(['service_id']);
                }
                if (Schema::hasColumn('loyalty_rules', 'service_category_id')) {
                    $table->dropForeign(['service_category_id']);
                }
            });
        }

        if (Schema::hasTable('package_items') && Schema::hasColumn('package_items', 'service_id')) {
            Schema::table('package_items', function (Blueprint $table) {
                $table->dropForeign(['service_id']);
            });
        }

        if (Schema::hasTable('package_session_usages') && Schema::hasColumn('package_session_usages', 'service_id')) {
            Schema::table('package_session_usages', function (Blueprint $table) {
                $table->dropForeign(['service_id']);
            });
        }

        if (Schema::hasTable('service_equipment_requirements')) {
            Schema::table('service_equipment_requirements', function (Blueprint $table) {
                $table->dropForeign(['service_id']);
            });
        }
    }

    private function renameColumnsReverse(): void
    {
        // Reverse all column renames
        if (Schema::hasColumn('appointments', 'service_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->renameColumn('service_id', 'treatment_id');
            });
        }

        if (Schema::hasTable('waitlist') && Schema::hasColumn('waitlist', 'service_id')) {
            Schema::table('waitlist', function (Blueprint $table) {
                $table->renameColumn('service_id', 'treatment_id');
            });
        }

        if (Schema::hasTable('staff_commissions')) {
            if (Schema::hasColumn('staff_commissions', 'service_id')) {
                Schema::table('staff_commissions', function (Blueprint $table) {
                    $table->renameColumn('service_id', 'treatment_id');
                });
            }
            if (Schema::hasColumn('staff_commissions', 'service_category_id')) {
                Schema::table('staff_commissions', function (Blueprint $table) {
                    $table->renameColumn('service_category_id', 'treatment_category_id');
                });
            }
        }

        if (Schema::hasTable('patient_photos') && Schema::hasColumn('patient_photos', 'service_id')) {
            Schema::table('patient_photos', function (Blueprint $table) {
                $table->renameColumn('service_id', 'treatment_id');
            });
        }

        if (Schema::hasTable('loyalty_rules')) {
            if (Schema::hasColumn('loyalty_rules', 'service_id')) {
                Schema::table('loyalty_rules', function (Blueprint $table) {
                    $table->renameColumn('service_id', 'treatment_id');
                });
            }
            if (Schema::hasColumn('loyalty_rules', 'service_category_id')) {
                Schema::table('loyalty_rules', function (Blueprint $table) {
                    $table->renameColumn('service_category_id', 'treatment_category_id');
                });
            }
        }

        if (Schema::hasTable('invoice_lines') && Schema::hasColumn('invoice_lines', 'service_id')) {
            Schema::table('invoice_lines', function (Blueprint $table) {
                $table->renameColumn('service_id', 'treatment_id');
            });
        }

        if (Schema::hasTable('package_items') && Schema::hasColumn('package_items', 'service_id')) {
            Schema::table('package_items', function (Blueprint $table) {
                $table->renameColumn('service_id', 'treatment_id');
            });
        }

        if (Schema::hasTable('package_session_usages') && Schema::hasColumn('package_session_usages', 'service_id')) {
            Schema::table('package_session_usages', function (Blueprint $table) {
                $table->renameColumn('service_id', 'treatment_id');
            });
        }

        Schema::table('service_branch_pricing', function (Blueprint $table) {
            $table->renameColumn('service_id', 'treatment_id');
        });

        if (Schema::hasTable('service_equipment_requirements')) {
            Schema::table('service_equipment_requirements', function (Blueprint $table) {
                $table->renameColumn('service_id', 'treatment_id');
            });
        }

        if (Schema::hasTable('tenant_usage') && Schema::hasColumn('tenant_usage', 'services')) {
            Schema::table('tenant_usage', function (Blueprint $table) {
                $table->renameColumn('services', 'treatments');
            });
        }
    }

    private function recreateForeignKeysReverse(): void
    {
        // Recreate original foreign keys
        Schema::table('treatments', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('treatment_categories')
                ->onDelete('set null');
        });

        Schema::table('treatment_branch_pricing', function (Blueprint $table) {
            $table->foreign('treatment_id')
                ->references('id')
                ->on('treatments')
                ->onDelete('cascade');
        });

        Schema::table('treatment_categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('treatment_categories')
                ->onDelete('set null');
        });

        if (Schema::hasColumn('appointments', 'treatment_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->foreign('treatment_id')
                    ->references('id')
                    ->on('treatments')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasTable('waitlist') && Schema::hasColumn('waitlist', 'treatment_id')) {
            Schema::table('waitlist', function (Blueprint $table) {
                $table->foreign('treatment_id')
                    ->references('id')
                    ->on('treatments')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasTable('staff_commissions')) {
            Schema::table('staff_commissions', function (Blueprint $table) {
                if (Schema::hasColumn('staff_commissions', 'treatment_id')) {
                    $table->foreign('treatment_id')
                        ->references('id')
                        ->on('treatments')
                        ->onDelete('set null');
                }
                if (Schema::hasColumn('staff_commissions', 'treatment_category_id')) {
                    $table->foreign('treatment_category_id')
                        ->references('id')
                        ->on('treatment_categories')
                        ->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('loyalty_rules')) {
            Schema::table('loyalty_rules', function (Blueprint $table) {
                if (Schema::hasColumn('loyalty_rules', 'treatment_id')) {
                    $table->foreign('treatment_id')
                        ->references('id')
                        ->on('treatments')
                        ->onDelete('set null');
                }
                if (Schema::hasColumn('loyalty_rules', 'treatment_category_id')) {
                    $table->foreign('treatment_category_id')
                        ->references('id')
                        ->on('treatment_categories')
                        ->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('package_items') && Schema::hasColumn('package_items', 'treatment_id')) {
            Schema::table('package_items', function (Blueprint $table) {
                $table->foreign('treatment_id')
                    ->references('id')
                    ->on('treatments')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasTable('package_session_usages') && Schema::hasColumn('package_session_usages', 'treatment_id')) {
            Schema::table('package_session_usages', function (Blueprint $table) {
                $table->foreign('treatment_id')
                    ->references('id')
                    ->on('treatments')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasTable('treatment_equipment_requirements')) {
            Schema::table('treatment_equipment_requirements', function (Blueprint $table) {
                $table->foreign('treatment_id')
                    ->references('id')
                    ->on('treatments')
                    ->onDelete('cascade');
            });
        }
    }
};
