<?php

namespace Modules\Booking\Filament\Resources\VisitResource\Pages;

use Filament\Actions;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Modules\Booking\Filament\Pages\Checkout;
use Modules\Booking\Filament\Resources\VisitResource;
use Modules\Booking\Models\Visit;
use Modules\Evaluations\Models\Evaluation;

class ViewVisit extends ViewRecord
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('checkout')
                ->label(__('booking::visits.actions.checkout'))
                ->icon('heroicon-o-shopping-cart')
                ->color('success')
                ->url(fn (Visit $record) => Checkout::getUrl(['visit_id' => $record->id]))
                ->visible(fn (Visit $record) => $record->canCheckout()),

            Actions\Action::make('view_invoice')
                ->label(__('booking::visits.actions.view_invoice'))
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn (Visit $record) => $record->invoice_id
                    ? \Modules\Billing\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $record->invoice_id])
                    : null
                )
                ->visible(fn (Visit $record) => $record->invoice_id !== null),

            Actions\Action::make('take_review')
                ->label(__('evaluations::evaluations.actions.take_review'))
                ->icon('heroicon-o-star')
                ->color('warning')
                ->form([
                    Section::make(__('evaluations::evaluations.sections.ratings'))
                        ->schema([
                            Radio::make('overall_rating')
                                ->label(__('evaluations::evaluations.fields.overall_rating'))
                                ->options([
                                    5 => '★★★★★ - '.__('evaluations::evaluations.form.overall_rating_helper'),
                                    4 => '★★★★☆ - Good',
                                    3 => '★★★☆☆ - Average',
                                    2 => '★★☆☆☆ - Poor',
                                    1 => '★☆☆☆☆ - Very Poor',
                                ])
                                ->required()
                                ->inline()
                                ->inlineLabel(false),

                            Radio::make('service_quality_rating')
                                ->label(__('evaluations::evaluations.fields.service_quality_rating'))
                                ->options([
                                    5 => '★★★★★',
                                    4 => '★★★★☆',
                                    3 => '★★★☆☆',
                                    2 => '★★☆☆☆',
                                    1 => '★☆☆☆☆',
                                ])
                                ->inline()
                                ->inlineLabel(false),

                            Radio::make('staff_friendliness_rating')
                                ->label(__('evaluations::evaluations.fields.staff_friendliness_rating'))
                                ->options([
                                    5 => '★★★★★',
                                    4 => '★★★★☆',
                                    3 => '★★★☆☆',
                                    2 => '★★☆☆☆',
                                    1 => '★☆☆☆☆',
                                ])
                                ->inline()
                                ->inlineLabel(false),

                            Radio::make('cleanliness_rating')
                                ->label(__('evaluations::evaluations.fields.cleanliness_rating'))
                                ->options([
                                    5 => '★★★★★',
                                    4 => '★★★★☆',
                                    3 => '★★★☆☆',
                                    2 => '★★☆☆☆',
                                    1 => '★☆☆☆☆',
                                ])
                                ->inline()
                                ->inlineLabel(false),

                            Radio::make('wait_time_rating')
                                ->label(__('evaluations::evaluations.fields.wait_time_rating'))
                                ->options([
                                    5 => '★★★★★',
                                    4 => '★★★★☆',
                                    3 => '★★★☆☆',
                                    2 => '★★☆☆☆',
                                    1 => '★☆☆☆☆',
                                ])
                                ->inline()
                                ->inlineLabel(false),
                        ])
                        ->columns(1),

                    Section::make(__('evaluations::evaluations.sections.nps'))
                        ->schema([
                            Radio::make('satisfaction_score')
                                ->label(__('evaluations::evaluations.fields.satisfaction_score'))
                                ->helperText(__('evaluations::evaluations.form.satisfaction_score_helper'))
                                ->options(array_combine(range(0, 10), range(0, 10)))
                                ->inline()
                                ->inlineLabel(false),

                            Toggle::make('would_recommend')
                                ->label(__('evaluations::evaluations.fields.would_recommend'))
                                ->helperText(__('evaluations::evaluations.form.would_recommend_helper')),
                        ]),

                    Section::make(__('evaluations::evaluations.sections.feedback'))
                        ->schema([
                            Textarea::make('feedback_text')
                                ->label(__('evaluations::evaluations.fields.feedback_text'))
                                ->placeholder(__('evaluations::evaluations.form.feedback_placeholder'))
                                ->rows(3),

                            Textarea::make('improvement_suggestions')
                                ->label(__('evaluations::evaluations.fields.improvement_suggestions'))
                                ->placeholder(__('evaluations::evaluations.form.suggestions_placeholder'))
                                ->rows(3),
                        ]),
                ])
                ->action(function (array $data, Visit $record): void {
                    Evaluation::create([
                        'tenant_id' => $record->tenant_id,
                        'branch_id' => $record->branch_id,
                        'visit_id' => $record->id,
                        'patient_id' => $record->patient_id,
                        'overall_rating' => $data['overall_rating'],
                        'service_quality_rating' => $data['service_quality_rating'] ?? null,
                        'staff_friendliness_rating' => $data['staff_friendliness_rating'] ?? null,
                        'cleanliness_rating' => $data['cleanliness_rating'] ?? null,
                        'wait_time_rating' => $data['wait_time_rating'] ?? null,
                        'satisfaction_score' => $data['satisfaction_score'] ?? null,
                        'would_recommend' => $data['would_recommend'] ?? null,
                        'feedback_text' => $data['feedback_text'] ?? null,
                        'improvement_suggestions' => $data['improvement_suggestions'] ?? null,
                        'source' => Evaluation::SOURCE_STAFF,
                    ]);

                    Notification::make()
                        ->title(__('evaluations::evaluations.messages.evaluation_created'))
                        ->success()
                        ->send();
                })
                ->visible(fn (Visit $record) => in_array($record->status, [Visit::STATUS_COMPLETED, Visit::STATUS_INVOICED]) &&
                    ! Evaluation::where('visit_id', $record->id)->exists()
                ),

            Actions\Action::make('view_evaluation')
                ->label(__('evaluations::evaluations.actions.view_evaluation'))
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(function (Visit $record) {
                    $evaluation = Evaluation::where('visit_id', $record->id)->first();

                    return $evaluation
                        ? \Modules\Evaluations\Filament\Resources\EvaluationResource::getUrl('view', ['record' => $evaluation->id])
                        : null;
                })
                ->visible(fn (Visit $record) => Evaluation::where('visit_id', $record->id)->exists()),
        ];
    }
}
