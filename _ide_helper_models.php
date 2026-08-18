<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int|null $tournament_id
 * @property int|null $player_id
 * @property \App\Enums\AchievementType $type
 * @property int|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Player|null $player
 * @property-read \App\Models\Tournament|null $tournament
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement wherePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereValue($value)
 */
	class Achievement extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $tournament_id
 * @property int|null $player1_id
 * @property int|null $player2_id
 * @property int|null $player1_score
 * @property int|null $player2_score
 * @property int|null $winner_id
 * @property int $group_number
 * @property \App\Enums\GameStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Player|null $player1
 * @property-read \App\Models\Player|null $player2
 * @property-read \App\Models\Tournament|null $tournament
 * @property-read \App\Models\Player|null $winner
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereGroupNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game wherePlayer1Id($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game wherePlayer1Score($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game wherePlayer2Id($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game wherePlayer2Score($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereWinnerId($value)
 */
	class Game extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $tournament_id
 * @property int $group_number
 * @property int $player_id
 * @property int $matches_played
 * @property int $matches_won
 * @property int $matches_lost
 * @property int $legs_won
 * @property int $legs_lost
 * @property int $points
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $place
 * @property int|null $legs_difference
 * @property \App\Enums\GameStatus $status
 * @property-read \App\Models\Player $player
 * @property-read \App\Models\Tournament $tournament
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding whereGroupNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding whereLegsDifference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding whereLegsLost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding whereLegsWon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding whereMatchesLost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding whereMatchesPlayed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding whereMatchesWon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding wherePlace($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding wherePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding wherePoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStanding whereUpdatedAt($value)
 */
	class GroupStanding extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $description
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $admins
 * @property-read int|null $admins_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Player> $guests
 * @property-read int|null $guests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $relatedUsers
 * @property-read int|null $related_users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Season> $seasons
 * @property-read int|null $seasons_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereUpdatedAt($value)
 */
	class Organization extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $code
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $tournament_id
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \App\Models\Tournament $tournament
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginCode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginCode whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginCode whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginCode whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginCode whereUpdatedAt($value)
 */
	class LoginCode extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $organization_id
 * @property int|null $season_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Achievement> $achievements
 * @property-read int|null $achievements_count
 * @property-read \App\Models\Organization|null $organization
 * @property-read \App\Models\Season|null $season
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereSeasonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Player whereUserId($value)
 */
	class Player extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $admins
 * @property-read int|null $admins_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Player> $guests
 * @property-read int|null $guests_count
 * @property-read \App\Models\Organization|null $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $relatedUsers
 * @property-read int|null $related_users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tournament> $tournaments
 * @property-read int|null $tournaments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereUpdatedAt($value)
 */
	class Season extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $season_id
 * @property string $name
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \App\Enums\TournamentStatus $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Achievement> $achievements
 * @property-read int|null $achievements_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Game> $games
 * @property-read int|null $games_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GroupStanding> $groupStandings
 * @property-read int|null $group_standings_count
 * @property-read \App\Models\Season|null $season
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereSeasonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereUpdatedAt($value)
 */
	class Tournament extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $role
 * @property int $can_create_organizations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Organization> $adminOrganizations
 * @property-read int|null $admin_organizations_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\Player|null $player
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Organization> $relatedOrganizations
 * @property-read int|null $related_organizations_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCanCreateOrganizations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

