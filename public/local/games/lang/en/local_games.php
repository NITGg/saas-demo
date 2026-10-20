<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English strings for local_games.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Games Corner';

// Capabilities.
$string['games:play'] = 'Open the Games Corner and play';
$string['games:viewreports'] = 'See what other learners have played';

// Hub page.
$string['hubtitle'] = 'Games Corner';
$string['hubintro'] = 'Short games that teach something. Play, collect points, earn badges.';
$string['yourpoints'] = 'Your points';
$string['yourbadges'] = 'Your badges';
$string['comingsoon'] = 'Coming soon';
$string['play'] = 'Play';
$string['cancel'] = 'Not now';
$string['playagain'] = 'Play again';
$string['backtohub'] = 'Back to the games';
$string['bestscore'] = 'Best: {$a}';
$string['nogamesyet'] = 'No games here yet.';

// Hub sections.
$string['cat_numbers'] = 'Numbers';
$string['cat_letters'] = 'Letters and words';
$string['cat_quiz'] = 'Questions';
$string['cat_memory'] = 'Memory and thinking';
$string['cat_motion'] = 'Moving games';

// The catalogue.
$string['game_math_race'] = 'Math Race';
$string['gamedesc_math_race'] = 'Add, subtract and multiply - pick the right answer and keep the race going.';
$string['game_math_catcher'] = 'Number Catcher';
$string['gamedesc_math_catcher'] = 'Numbers fall like rain. Move the basket and catch only the ones that fit.';
$string['game_math_shop'] = 'Math Shop';
$string['gamedesc_math_shop'] = 'Buy things, work out the price and the change.';
$string['game_letter_order'] = 'Letter Order';
$string['gamedesc_letter_order'] = 'The letters of a word are jumbled - put them back in order.';
$string['game_word_builder'] = 'Word Builder';
$string['gamedesc_word_builder'] = 'A pile of letters. Build as many words as you can.';
$string['game_match_connect'] = 'Match the Picture';
$string['gamedesc_match_connect'] = 'Drag each word under the picture it belongs to.';
$string['game_crossword'] = 'Crossword';
$string['gamedesc_crossword'] = 'Clues across and down.';
$string['game_word_search'] = 'Word Search';
$string['gamedesc_word_search'] = 'A grid of letters with words hidden inside it.';
$string['game_speak_words'] = 'Say the Word';
$string['gamedesc_speak_words'] = 'Say the word out loud and the microphone checks it.';
$string['game_quiz'] = 'General Knowledge';
$string['gamedesc_quiz'] = 'A question and three or four answers.';
$string['game_true_false'] = 'True or False';
$string['gamedesc_true_false'] = 'A sentence - is it true or false?';
$string['game_xo_quiz'] = 'Tic-Tac-Toe Quiz';
$string['gamedesc_xo_quiz'] = 'Every right answer earns you a square.';
$string['game_target_answer'] = 'Pick the Answer';
$string['gamedesc_target_answer'] = 'Answers move across targets - hit the right one.';
$string['game_balloon_pop'] = 'Balloon Pop';
$string['gamedesc_balloon_pop'] = 'Balloons carry numbers and letters. Pop the one asked for.';
$string['game_wheel'] = 'Question Wheel';
$string['gamedesc_wheel'] = 'The wheel spins and picks the topic of your question.';
$string['game_space_quiz'] = 'Space Trip';
$string['gamedesc_space_quiz'] = 'Every right answer moves the rocket further.';
$string['game_who_am_i'] = 'Who Am I?';
$string['gamedesc_who_am_i'] = 'Clues appear one by one - guess before they run out.';
$string['game_memory_cards'] = 'Memory Cards';
$string['gamedesc_memory_cards'] = 'Face-down cards. Find the matching pairs.';
$string['game_puzzle'] = 'Jigsaw';
$string['gamedesc_puzzle'] = 'Put a picture back together piece by piece.';
$string['game_find_difference'] = 'Spot the Difference';
$string['gamedesc_find_difference'] = 'Two pictures - find what changed.';
$string['game_color_challenge'] = 'Colour Challenge';
$string['gamedesc_color_challenge'] = 'Pick the right colour, or match colours together.';
$string['game_runner'] = 'Learning Run';
$string['gamedesc_runner'] = 'Collect the right answers and dodge the wrong ones.';

// Badges.
$string['badge_fast_calculator'] = 'Sharp Calculator';
$string['badgehint_fast_calculator'] = '10 correct answers in a row';
$string['badge_sharp_hunter'] = 'Skilled Hunter';
$string['badgehint_sharp_hunter'] = '20 numbers caught without a single mistake';

// Shared in-game wording.
$string['js_start'] = 'Start';
$string['js_correct'] = 'Well done!';
$string['js_wrong'] = 'Try again';
$string['js_score'] = 'Score';
$string['js_streak'] = 'In a row';
$string['js_lives'] = 'Tries';
$string['js_roundover'] = 'Round finished!';
$string['js_yougot'] = 'You collected {$a} points';
$string['js_newbadge'] = 'New badge!';
$string['js_saving'] = 'Saving...';
$string['js_savefailed'] = 'Your points could not be saved - the game still counts.';
$string['js_sound_on'] = 'Sound on';
$string['js_sound_off'] = 'Sound off';
// The two placeholders are filled in the browser, not by get_string.
$string['js_progress'] = '{$a} of {$b}';
$string['js_progresslabel'] = 'Where you are in the round';

// Math Race. The operator words are what the voice reads out loud - a screen
// reader and a speech engine both make nothing of "x".
$string['js_op_plus'] = 'plus';
$string['js_op_minus'] = 'minus';
$string['js_op_times'] = 'times';
$string['js_race_question'] = 'What is the answer?';
$string['js_math_race_ready'] = 'Ready for the race?';
$string['js_math_race_howto'] = 'A sum appears, three answers under it. Tap the right one and the runner moves forward.';

// Number Catcher.
$string['js_math_catcher_ready'] = 'Ready to hunt?';
$string['js_math_catcher_howto'] = 'Move the basket with the arrows, your finger or the big buttons. Catch only the numbers that match what is asked.';
$string['js_catch_rule_equals'] = 'Catch the ones equal to {$a}';
$string['js_catch_rule_divisible'] = 'Catch the ones that divide by {$a}';
$string['js_catch_rule_greater'] = 'Catch the ones bigger than {$a}';
$string['js_catch_rule_less'] = 'Catch the ones smaller than {$a}';
$string['js_catch_rule_even'] = 'Catch the even numbers';
$string['js_catch_rule_odd'] = 'Catch the odd numbers';
$string['js_catch_left'] = 'Left';
$string['js_catch_right'] = 'Right';

// -----------------------------------------------------------------------------
// The word bank. Every letters game reads from this one list, so a translator
// swaps the whole vocabulary of six games by editing one string.
//
// One word per line: word|emoji|clue
// -----------------------------------------------------------------------------
$string['wordbank'] = 'cat|🐱|It says meow and loves milk
dog|🐶|A loyal animal that barks
lion|🦁|The king of the jungle
fish|🐟|It lives in the water
horse|🐴|A fast animal people ride
cow|🐮|It gives us milk
bee|🐝|It makes the honey
apple|🍎|A red fruit
banana|🍌|A long yellow fruit
grapes|🍇|A fruit with small round berries
bread|🍞|We eat it with almost everything
milk|🥛|A white drink from the cow
honey|🍯|Sweet, and the bee makes it
house|🏠|The place where we live
school|🏫|The place where we learn
door|🚪|We come into a room through it
book|📚|We read it
pen|✏️|We write with it
sun|☀️|It lights up the day
moon|🌙|It shines at night
star|⭐|It twinkles in the sky
rain|🌧️|Water falling from the clouds
tree|🌳|It has a trunk and leaves
rose|🌹|A flower that smells sweet
sea|🌊|A big body of salty water
mountain|⛰️|A high place on the land
fire|🔥|It is hot and it glows
car|🚗|It runs on four wheels
train|🚂|It runs on rails
plane|✈️|It flies in the sky
boat|⛵|It travels on the water
clock|⏰|It tells us the time
key|🔑|We open the door with it
ball|⚽|We play with it
bed|🛏️|We sleep on it
window|🪟|We look outside through it
eye|👁️|We see with it';

// -----------------------------------------------------------------------------
// The wider vocabulary, for Word Builder only.
//
// The word bank above has to earn its keep twice - every entry needs a picture
// and a clue - so it stays small. Word Builder does not need either: it only
// needs to know whether what the child built is a word. Judging it against the
// picture bank alone meant a child could spell a perfectly good word and be
// told it was not one, which is the one thing this game must never do.
//
// Space separated; order does not matter.
// -----------------------------------------------------------------------------
$string['wordlist'] = 'ant arm art bag bat bed bee bin box boy bus cab can cap car cat cow cup cut dad
day dog dot ear eat egg end eye fan far fat fin fly fox fun gas get gum gun hat hen hit hot ice ink
jam jar jet job key kid lap leg lid lip log lot low man map mat men mix mud nap net new nut oil old
one out owl pan pat pen pet pig pin pot pup ram rat red rib rug run sad sea set sit six sky son sun
tap tar ten tie tin toe ton top toy tub two van war wax web wet win zip
bake ball band bank barn base bath bean bear beat bell belt bird blue boat body bone book boot bowl
cake calm camp card care cart case cave city coat coin cold cook cool corn cost crab cube dark deer
desk dish door down duck dust east easy face fall farm fast fire fish flag food foot fork four frog
game gate gift girl give glad goat gold good grow hair half hand hard head heat help hill hold home
hope horn hour idea iron jump keep kind king lake lamp land last leaf left lion list long look love
milk mind moon most move name nest nice noon nose note oven pack page pain palm park path pear pink
plan play plum pond pool post rain read rest ride ring road rock rose rule sail salt sand seat shop
sing skin snow soap sock song star stop swim tail take talk tall team tent time tree true tube turn
wall warm wash wave week wind wing wolf wood word work yard year zero
apple beach bread brave brush chair chalk chase clean clear cloud crown dance dream dress drink earth
field flame flour fruit glass grape grass green heart horse house juice knife light money mouse music
night ocean paint paper party peach place plane plant point queen quiet river robot round sheep shell
shine shirt short sleep small smile snake sound space spoon stone storm sugar sweet table teeth think
tiger toast today tooth touch towel train under water wheel white whale world write young';

// -----------------------------------------------------------------------------
// The question bank.
//
// The design doc calls General Knowledge "the most important game", because its
// questions are what XO, Balloon Pop, Pick the Answer, the Question Wheel,
// Space Trip and Learning Run are all made of. So it lives here once, and six
// games read it.
//
// One question per line: topic|question|right answer|wrong|wrong|wrong
// -----------------------------------------------------------------------------
$string['quizbank'] = 'math|What is 7 + 5?|12|10|13|11
math|What is 9 x 3?|27|24|21|29
math|What is half of twenty?|10|5|15|12
math|What is 20 - 8?|12|14|10|13
math|How many sides has a triangle?|3|4|5|6
math|How many sides has a square?|4|3|5|6
math|What is 6 x 6?|36|30|42|32
math|What is 100 divided by 4?|25|20|30|24
math|Which number is bigger?|71|17|27|7
math|What is 5 + 5 + 5?|15|10|20|12
science|Which is the largest planet?|Jupiter|Earth|Mars|Mercury
science|Where does the sun rise?|In the east|In the west|In the north|In the south
science|What do we breathe in?|Oxygen|Nitrogen|Helium|Carbon dioxide
science|At what temperature does water freeze?|0|100|50|10
science|How many planets are in the solar system?|8|7|9|10
science|What lights up the day?|The sun|The moon|The stars|A lamp
science|What do plants make their food with?|Sunlight|Sand|Cold air|Salt water
science|Which is faster, light or sound?|Light|Sound|They are the same|The wind
science|At what temperature does water boil?|100|50|70|200
science|Which planet is closest to the sun?|Mercury|Earth|Saturn|Mars
language|What is the plural of "book"?|books|bookes|bookies|booken
language|What is the opposite of "big"?|small|tall|wide|old
language|How many letters are in the English alphabet?|26|24|28|30
language|What is the opposite of "day"?|night|morning|sun|light
language|What is the first letter of "sun"?|s|u|n|t
language|What is the opposite of "fast"?|slow|strong|new|high
language|What is the plural of "child"?|children|childs|childes|childrens
language|How many letters are in the word "school"?|6|5|7|8
language|What is the opposite of "above"?|below|beside|in front|behind
language|What is the last letter of "moon"?|n|m|o|d
animals|Which is the largest animal on earth?|The blue whale|The elephant|The giraffe|The lion
animals|Which animal has the longest neck?|The giraffe|The camel|The horse|The elephant
animals|Who is the king of the jungle?|The lion|The tiger|The bear|The wolf
animals|Which animal makes honey?|The bee|The butterfly|The ant|The fly
animals|Which animal hops and has a pouch?|The kangaroo|The rabbit|The frog|The monkey
animals|Which of these lives in water?|The fish|The cat|The bird|The lion
animals|What does a camel store in its hump?|Fat|Water|Sand|Air
animals|How many legs has a spider?|8|6|4|10
animals|Which animal changes colour?|The chameleon|The rabbit|The duck|The sheep
animals|Which animal runs the fastest?|The cheetah|The horse|The dog|The gazelle';

// True or False, game 11. One per line: statement|1 for true 0 for false|why.
$string['tfbank'] = 'The sun rises in the west|0|The sun rises in the east
An elephant is bigger than a mouse|1|Elephants are among the largest animals
Water boils at 100 degrees|1|That is right, 100 degrees Celsius
Cats bark|0|Cats meow - dogs bark
There are 7 days in a week|1|Right, Monday to Sunday
The earth is round|1|Right, the earth is a sphere
Fish fly in the sky|0|Fish swim in the water
Bees make honey|1|Right, and their work matters a great deal
Snow is hot|0|Snow is very cold
There are 12 months in a year|1|Right, January to December
Spiders have 8 legs|1|Right, eight legs
Penguins fly|0|Penguins swim well, but they do not fly
Carrots are good for your eyes|1|Right, they carry a vitamin your eyes need
Winter is colder than summer|1|Right, winter is the cold season
The moon makes its own light|0|The moon reflects the light of the sun
Trees drink water|1|Right, through their roots
Lions eat grass|0|Lions eat meat
A triangle has 3 sides|1|Right, three sides
Sand breathes|0|Sand is not alive
A plane is faster than a car|1|Right, far faster';

// Who Am I, game 17. One per line: answer|emoji|clue|clue|clue.
$string['whoami'] = 'cow|🐄|I have four legs|I eat grass all day|I give you milk
lion|🦁|I live in the jungle|I have a mane around my face|They call me the king
bee|🐝|I am small and I fly|I visit the flowers|I make the honey
elephant|🐘|My body is big and strong|My ears are very large|I have a long trunk
fish|🐟|I never walk|I live in the water|I have fins
giraffe|🦒|I live in Africa|I eat from the tall trees|My neck is the longest of all
cat|🐱|I sleep a great deal|I love milk|I say meow
rabbit|🐰|I hop instead of walking|My ears are long|I love carrots
bird|🐦|I have two wings|I build a nest|I sing in the morning
sun|☀️|I rise every morning|I give warmth and light|Without me the world is dark';

// Colours for game 21. One per line: name|hex.
$string['colourbank'] = 'red|#e04b4b
blue|#4b7be0
green|#3fa877
yellow|#e0c84b
orange|#e0894b
purple|#9b5fd0
pink|#e07ab0
brown|#8a5a3c';

// Shop shelf for game 03. One item per line: emoji|name.
$string['shopitems'] = '🍎|apple
🥛|milk
🍞|bread
🧀|cheese
🍌|banana
🥚|egg
🍫|chocolate
🧃|juice
🍯|honey
✏️|pen
📚|book
⚽|ball';

// Math Shop.
$string['js_math_shop_ready'] = 'Off to the shop?';
$string['js_math_shop_howto'] = 'You have some money and a shopping list. Work out the total first, then the change.';
$string['js_shop_youhave'] = 'You have {$a} pounds';
$string['js_shop_total'] = 'What is the total?';
$string['js_shop_change'] = 'And the change?';
$string['js_shop_pound'] = 'pounds';

// Letter Order.
$string['js_letter_order_ready'] = 'Ready to spell?';
$string['js_letter_order_howto'] = 'A picture appears with its letters jumbled underneath. Tap the letters in the right order to build the word.';
$string['js_order_clear'] = 'Clear';
$string['js_order_hint'] = 'This word has {$a} letters';

// Word Builder.
$string['js_word_builder_ready'] = 'Ready to build words?';
$string['js_word_builder_howto'] = 'Seven letters are in front of you. Tap them in order to make a word, then press Enter. Longer words are worth more.';
$string['js_build_submit'] = 'Enter';
$string['js_build_clear'] = 'Clear';
$string['js_build_next'] = 'New letters';
$string['js_build_hint'] = '💡 Hint';
$string['js_build_targets'] = 'Words to find';
$string['js_build_nohints'] = 'No more hints right now';
$string['js_build_found'] = 'You found {$a} words';
$string['js_build_already'] = 'You already found that one';
$string['js_build_notaword'] = 'That is not a word we know';

// Match the Picture.
$string['js_match_connect_ready'] = 'Ready to match?';
$string['js_match_connect_howto'] = 'Drag each word under its picture - or tap the word, then tap the picture.';
$string['js_match_drop'] = 'Put the word here';

// Crossword.
$string['js_crossword_ready'] = 'Ready for the puzzle?';
$string['js_crossword_howto'] = 'Tap a square in the grid, read its clue, then tap the letters to write the word.';
$string['js_cross_across'] = 'Across';
$string['js_cross_down'] = 'Down';
$string['js_cross_pickcell'] = 'Tap any square to see its clue';
$string['js_cross_backspace'] = 'Delete a letter';

// Word Search.
$string['js_word_search_ready'] = 'Ready to search?';
$string['js_word_search_howto'] = 'The words are hidden in the grid - across, down or diagonally. Drag from the first letter to the last.';
$string['js_search_remaining'] = '{$a} left';

// Say the Word. The microphone notice is required by the design doc: the child
// must be told before the browser is ever asked for the mic.
$string['js_speak_words_ready'] = 'This game uses the microphone';
$string['js_speak_words_howto'] = 'It listens to you to check how you say the word. The browser will ask your permission the first time. Nothing is recorded and nothing is stored anywhere. Find a quiet spot and start.';
$string['js_speak_tap'] = 'Tap and speak';
$string['js_speak_listening'] = 'Listening...';
$string['js_speak_heard'] = 'I heard: {$a}';
$string['js_speak_saythis'] = 'Say this word';
$string['js_speak_nomic'] = 'This browser cannot listen through the microphone. Try Chrome on a computer or on Android.';
$string['js_speak_denied'] = 'The microphone could not be opened. Allow the browser to use it and try again.';

// Shared by every game built on the question bank.
$string['js_answer_because'] = '{$a}';
$string['js_topic_math'] = 'Maths';
$string['js_topic_science'] = 'Science';
$string['js_topic_language'] = 'Language';
$string['js_topic_animals'] = 'Animals';

// General Knowledge.
$string['js_quiz_ready'] = 'Ready for some questions?';
$string['js_quiz_howto'] = 'A question appears with answers under it. Tap the one you think is right - and getting it wrong costs nothing.';

// True or False.
$string['js_true_false_ready'] = 'True or false?';
$string['js_true_false_howto'] = 'A sentence appears. Tap the tick if it is true and the cross if it is not. Either way you find out why.';
$string['js_tf_true'] = 'True';
$string['js_tf_false'] = 'False';

// XO Quiz.
$string['js_xo_quiz_ready'] = 'Fancy a game of noughts and crosses?';
$string['js_xo_quiz_howto'] = 'Pick an empty square and answer its question. Get it right and your mark goes in. Three in a line wins.';
$string['js_xo_yourturn'] = 'Your turn - pick a square';
$string['js_xo_thinking'] = 'The computer is thinking...';
$string['js_xo_youwin'] = 'You won that one! 🎉';
$string['js_xo_youlose'] = 'The computer took that one';
$string['js_xo_draw'] = 'A draw!';
$string['js_xo_missed'] = 'Not right - the turn goes to the computer';
$string['js_xo_round'] = 'Match {$a} of {$b}';

// Pick the Answer.
$string['js_target_answer_ready'] = 'Ready to aim?';
$string['js_target_answer_howto'] = 'The answers move across the screen. Tap the right one before it gets away - the quicker you are, the more it is worth.';

// Balloon Pop.
$string['js_balloon_pop_ready'] = 'Ready to pop?';
$string['js_balloon_pop_howto'] = 'Balloons float up carrying numbers. Pop only the ones that match what is asked at the top.';
$string['js_balloon_rule_even'] = 'Pop the even numbers';
$string['js_balloon_rule_odd'] = 'Pop the odd numbers';
$string['js_balloon_rule_greater'] = 'Pop the ones bigger than {$a}';
$string['js_balloon_rule_less'] = 'Pop the ones smaller than {$a}';
$string['js_balloon_rule_divisible'] = 'Pop the ones that divide by {$a}';
$string['js_balloon_rule_equals'] = 'Pop the ones equal to {$a}';

// Question Wheel.
$string['js_wheel_ready'] = 'Spin the wheel!';
$string['js_wheel_howto'] = 'Tap the wheel to spin it. It lands on a topic and a question comes from that topic. The wheel picks the subject, never a prize.';
$string['js_wheel_spin'] = 'Spin';
$string['js_wheel_landed'] = 'It landed on {$a}';
$string['js_wheel_topics'] = 'Topics you have answered';

// Space Trip.
$string['js_space_quiz_ready'] = 'Ready for lift-off?';
$string['js_space_quiz_howto'] = 'Every right answer moves the rocket one step closer to the planet. Reach it and a further planet opens up.';
$string['js_space_stage'] = 'Stage {$a}: {$b}';
$string['js_space_arrived'] = 'You reached {$a}! 🎉';
$string['js_space_moon'] = 'the Moon';
$string['js_space_mars'] = 'Mars';
$string['js_space_jupiter'] = 'Jupiter';

// Who Am I.
$string['js_who_am_i_ready'] = 'Can you guess who I am?';
$string['js_who_am_i_howto'] = 'One clue appears, and you can ask for a second and a third. The earlier you guess, the more it is worth.';
$string['js_who_hint'] = 'Another clue';
$string['js_who_worth'] = 'Worth {$a} points now';
$string['js_who_answer'] = 'The answer: {$a}';

// Memory Cards.
$string['js_memory_cards_ready'] = 'Ready to remember?';
$string['js_memory_cards_howto'] = 'Turn over two cards. A matching pair stays up; anything else turns back. Try to clear the board in as few flips as you can.';
$string['js_memory_flips'] = 'Flips: {$a}';

// Jigsaw.
$string['js_puzzle_ready'] = 'Ready for the jigsaw?';
$string['js_puzzle_howto'] = 'The picture breaks apart and shuffles. Tap two pieces to swap them until the picture is back together.';
$string['js_puzzle_peek'] = 'Show the picture';

// Spot the Difference.
$string['js_find_difference_ready'] = 'Ready to spot them?';
$string['js_find_difference_howto'] = 'The two pictures are almost the same. Tap the squares in the second one that changed.';
$string['js_diff_left'] = 'The original';
$string['js_diff_right'] = 'Look here';
$string['js_diff_remaining'] = '{$a} differences left';

// Colour Challenge.
$string['js_color_challenge_ready'] = 'Do you know your colours?';
$string['js_color_challenge_howto'] = 'You will be told a colour. Tap the circle that matches it.';
$string['js_colour_find'] = 'Find the colour {$a}';

// Learning Run.
$string['js_runner_ready'] = 'Ready to run?';
$string['js_runner_howto'] = 'The runner runs on its own and the question stays at the top. Steer left and right to collect the right answer and dodge the rest.';
$string['js_runner_hearts'] = 'Hearts';




// Badges for the new games.
$string['badge_smart_shopper'] = 'Smart Shopper';
$string['badgehint_smart_shopper'] = '5 purchases with the maths exactly right';
$string['badge_letter_king'] = 'Letter King';
$string['badgehint_letter_king'] = '15 words spelled correctly';
$string['badge_word_builder'] = 'Word Builder';
$string['badgehint_word_builder'] = '10 words in a single round';
$string['badge_sharp_eye'] = 'Sharp Eye';
$string['badgehint_sharp_eye'] = 'Every board with no mistake';
$string['badge_puzzle_solver'] = 'Puzzle Solver';
$string['badgehint_puzzle_solver'] = 'A full grid with no mistake';
$string['badge_falcon_eye'] = 'Falcon Eye';
$string['badgehint_falcon_eye'] = 'Every word found in one round';
$string['badge_clear_voice'] = 'Clear Voice';
$string['badgehint_clear_voice'] = '10 words said correctly';
$string['badge_know_it_all'] = 'Know It All';
$string['badgehint_know_it_all'] = '20 right answers';
$string['badge_focused'] = 'Focused';
$string['badgehint_focused'] = '15 right answers in a row';
$string['badge_xo_champion'] = 'Noughts and Crosses Champion';
$string['badgehint_xo_champion'] = '3 matches won';
$string['badge_sharp_shot'] = 'Sharp Shot';
$string['badgehint_sharp_shot'] = '10 targets hit in a row';
$string['badge_pop_master'] = 'Pop Master';
$string['badgehint_pop_master'] = '25 balloons popped correctly';
$string['badge_encyclopedia'] = 'Encyclopedia';
$string['badgehint_encyclopedia'] = 'A right answer from every topic on the wheel';
$string['badge_astronaut'] = 'Astronaut';
$string['badgehint_astronaut'] = 'Reaching the last planet';
$string['badge_good_detective'] = 'Good Detective';
$string['badgehint_good_detective'] = '5 answers from the first clue';
$string['badge_strong_memory'] = 'Strong Memory';
$string['badgehint_strong_memory'] = 'A full board in under 20 flips';
$string['badge_picture_builder'] = 'Picture Builder';
$string['badgehint_picture_builder'] = 'A 16-piece jigsaw completed';
$string['badge_detective_eye'] = 'Detective Eye';
$string['badgehint_detective_eye'] = 'Every difference, with no mistakes';
$string['badge_little_artist'] = 'Little Artist';
$string['badgehint_little_artist'] = 'Every colour right';
$string['badge_fast_runner'] = 'Fast Runner';
$string['badgehint_fast_runner'] = 'A whole stage without losing a heart';

// Errors.
$string['errorunknowngame'] = 'That game does not exist, or is not ready yet.';

// Privacy.
$string['privacy:metadata:progress'] = 'Points and play counts per game.';
$string['privacy:metadata:progress:userid'] = 'The user who played.';
$string['privacy:metadata:progress:gameid'] = 'Which game was played.';
$string['privacy:metadata:progress:points'] = 'Points collected in this game.';
$string['privacy:metadata:progress:plays'] = 'How many rounds were finished.';
$string['privacy:metadata:progress:bestscore'] = 'The best single-round score.';
$string['privacy:metadata:progress:beststreak'] = 'The longest run of correct answers.';
$string['privacy:metadata:progress:timemodified'] = 'When the last round was played.';
$string['privacy:metadata:badge'] = 'Badges earned in the Games Corner.';
$string['privacy:metadata:badge:userid'] = 'The user who earned the badge.';
$string['privacy:metadata:badge:gameid'] = 'The game the badge came from.';
$string['privacy:metadata:badge:badge'] = 'Which badge was earned.';
$string['privacy:metadata:badge:timeawarded'] = 'When the badge was earned.';

// -------------------------------------------------------------------------
// Game control: the administration screen under Site administration >
// Plugins > Local plugins. Nothing below this line is seen by a learner, so
// the register is the formal one used across the rest of the administration
// interface, not the register the games themselves speak in.
// -------------------------------------------------------------------------

$string['games:manage'] = 'Manage the games and their content';

$string['gamecontrol'] = 'Game control';
$string['gamecontrolintro'] = 'Manage the games available in the Games Corner. Each game may be renamed, enabled or disabled, and has its own content, which may be edited independently of every other game.';
$string['backtocontrol'] = 'Return to Game control';
$string['cornertotals'] = '{$a->games} games · {$a->rows} content entries · {$a->players} participants · {$a->plays} rounds completed · {$a->points} points awarded';

// The catalogue table.
$string['colgame'] = 'Game';
$string['collevel'] = 'Level';
$string['colcontent'] = 'Content';
$string['colplayers'] = 'Participants';
$string['colplays'] = 'Rounds';
$string['colstatus'] = 'Status';
$string['customised'] = 'Modified';
$string['statuslive'] = 'Enabled';
$string['statusoff'] = 'Disabled';
$string['rowcount'] = '{$a} entries';

// One game's page.
$string['gamesettings'] = 'Game settings';
$string['gamecontent'] = 'Game content';
$string['gamestats'] = '{$a->players} participants · {$a->plays} rounds · {$a->points} points · highest single round {$a->bestscore}';
$string['playthisgame'] = 'Open the game';

// The settings form.
$string['gamename'] = 'Name';
$string['gamename_help'] = 'The name shown on the game card and at the top of the game. Leave the field empty to use the name supplied with the plugin. An input is provided for each installed language.';
$string['gamedescription'] = 'Description';
$string['gamedescription_help'] = 'The single line shown beneath the game name on the card. Leave the field empty to use the description supplied with the plugin.';
$string['gameenabled'] = 'Enabled';
$string['gameenabled_help'] = 'A disabled game remains in the Games Corner as a "coming soon" card and cannot be opened. Rounds already completed retain their points: disabling a game hides it, it does not remove any participant\'s progress.';
$string['gamelogo'] = 'Logo';
$string['gamelogo_help'] = 'A picture shown for this game on its card in the Games Corner and on the game screen, in place of its emoji. A square image works best. Remove it to bring the emoji back.';
$string['gamelevel'] = 'Level';
$string['gamelevel_help'] = 'The one to three stars shown on the card. This is an indication for the participant only; no aspect of the game changes with it.';
$string['leveldefault'] = 'Default ({$a})';
$string['errornametoolong'] = 'The name is too long once all languages are stored together.';

// Content.
$string['addrow'] = 'Add entry';
$string['addrowto'] = 'Add an entry to {$a}';
$string['editrow'] = 'Edit an entry in {$a}';
$string['restoredefaults'] = 'Restore the supplied content';
$string['contentrestored'] = 'The supplied content has been restored: {$a} entries.';
$string['confirmrestore'] = 'Discard every content entry for this game and restore the entries supplied with the plugin? This cannot be undone. No other game is affected.';
$string['contentempty'] = 'This game has no content. It cannot be played until at least one entry is added, or the supplied content is restored.';
$string['confirmdeleterow'] = 'Delete this entry?';
$string['otherlanguageonly'] = 'Another language only';
$string['errorunknownrow'] = 'That entry does not belong to this game.';
$string['errorbadhex'] = 'Enter the colour as a hash followed by six hexadecimal digits, for example #e04b4b.';
$string['errorwrongisright'] = 'This is identical to the correct answer, which would present the participant with two correct options.';
$string['errorrangebackwards'] = 'The upper limit may not be lower than the lower limit.';

// What one entry of each shape holds.
$string['shapedesc_questions'] = 'A question, its correct answer and up to three incorrect answers. Three options are presented when only one incorrect answer is supplied, and four when there are more.';
$string['shapedesc_topicquestions'] = 'A question, its correct answer and up to three incorrect answers. Each question also carries a topic, which determines the segment of the wheel that leads to it.';
$string['shapedesc_words'] = 'A word, a picture for it and a single-line definition. Words are entered without diacritics: the letter games divide a word into one tile per letter, and a diacritic would occupy a tile of its own.';
$string['shapedesc_vocabulary'] = 'The wider list of words accepted as valid. These words are only checked against what the participant spells and are never displayed, so no picture or definition is required. Each language has its own list.';
$string['shapedesc_statements'] = 'A statement, whether it is true, and the explanation shown to the participant after either answer.';
$string['shapedesc_clues'] = 'An answer, a picture for it and three clues. The clues are revealed one at a time, so the least specific should be entered first.';
$string['shapedesc_colours'] = 'A colour name and its hexadecimal value.';
$string['shapedesc_shopitems'] = 'The items on the shelf in Math Shop: a picture and a name. Prices are calculated by the game.';
$string['shapedesc_sumrules'] = 'The arithmetic this game is permitted to ask. Each entry defines an operation and the range each operand is drawn from; the game generates questions continuously from the entries supplied. Removing an entry removes that kind of question.';
$string['shapedesc_numberrules'] = 'The rules this game is permitted to set. Each entry defines a rule and, where the rule requires a number, the range that number is drawn from. The game selects among the entries supplied.';

// Field labels.
$string['field_topic'] = 'Topic';
$string['field_topic_help'] = 'The segment of the wheel that leads to this question.';
$string['field_question'] = 'Question';
$string['field_answer'] = 'Correct answer';
$string['field_wrong1'] = 'Incorrect answer 1';
$string['field_wrong2'] = 'Incorrect answer 2';
$string['field_wrong3'] = 'Incorrect answer 3';
$string['field_word'] = 'Word';
$string['field_emoji'] = 'Picture';
$string['field_emoji_help'] = 'A single emoji. The games use no image files; the emoji is the picture.';
$string['field_clue'] = 'Definition';
$string['field_clue1'] = 'First clue';
$string['field_clue2'] = 'Second clue';
$string['field_clue3'] = 'Third clue';
$string['field_statement'] = 'Statement';
$string['field_istrue'] = 'Correct value';
$string['field_istrue_help'] = 'Whether the statement is true. The explanation is shown after either answer, so a false statement requires an explanation of the same quality as a true one.';
$string['field_why'] = 'Explanation';
$string['field_colourname'] = 'Colour name';
$string['field_hex'] = 'Hexadecimal value';
$string['field_hex_help'] = 'A hash followed by six hexadecimal digits, for example #e04b4b. This is the colour the participant sees, so it should be sufficiently distinct from the other colours entered.';
$string['field_itemname'] = 'Item name';
$string['field_op'] = 'Operation';
$string['field_mina'] = 'First number, from';
$string['field_maxa'] = 'First number, to';
$string['field_minb'] = 'Second number, from';
$string['field_maxb'] = 'Second number, to';
$string['field_kind'] = 'Rule';
$string['field_minn'] = 'Number, from';
$string['field_minn_help'] = 'The lower limit of the number used in the rule. Rules that use no number ignore this value.';
$string['field_maxn'] = 'Number, to';

// Fixed option lists.
$string['option_topics_math'] = 'Mathematics';
$string['option_topics_science'] = 'Science';
$string['option_topics_language'] = 'Language';
$string['option_topics_animals'] = 'Animals';
$string['option_ops_plus'] = 'Addition';
$string['option_ops_minus'] = 'Subtraction';
$string['option_ops_times'] = 'Multiplication';
$string['option_kinds_even'] = 'Even numbers';
$string['option_kinds_odd'] = 'Odd numbers';
$string['option_kinds_greater'] = 'Greater than a number';
$string['option_kinds_less'] = 'Less than a number';
$string['option_kinds_divisible'] = 'Divisible by a number';
$string['option_kinds_equals'] = 'Equal to a number';

$string['istrue_yes'] = 'True';
$string['istrue_no'] = 'False';
$string['addrowtitle'] = 'Add an entry';
$string['editrowtitle'] = 'Edit the entry';
