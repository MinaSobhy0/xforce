<svg viewBox="0 0 400 600" xmlns="http://www.w3.org/2000/svg" class="face-diagram-svg">
    <defs>
        <style>
            .face-region {
                fill: rgba(255, 255, 255, 0.05);
                stroke: rgba(99, 102, 241, 0.4);
                stroke-width: 1.5;
                stroke-dasharray: 3, 3;
                transition: all 0.2s ease;
                cursor: crosshair;
            }
            .face-region:hover {
                fill: rgba(99, 102, 241, 0.15);
                stroke: rgba(99, 102, 241, 0.8);
                stroke-width: 2.5;
                stroke-dasharray: none;
                filter: drop-shadow(0 0 8px rgba(99, 102, 241, 0.4));
            }
            .face-outline {
                fill: #f5d5c8;
                stroke: #d4a574;
                stroke-width: 2;
            }
            .muscle-line {
                stroke: #c89b7b;
                stroke-width: 1.5;
                fill: none;
                opacity: 0.6;
            }
            .feature {
                fill: #a67c52;
            }
            .eye {
                fill: #4a90a4;
            }
            .pupil {
                fill: #2c3e50;
            }
            .iris-highlight {
                fill: #ffffff;
                opacity: 0.3;
            }
        </style>
    </defs>

    <!-- Face Outline -->
    <ellipse class="face-outline" cx="200" cy="280" rx="140" ry="190"/>

    <!-- Neck -->
    <path class="face-outline" d="M 160 440 Q 160 480 180 520 L 220 520 Q 240 480 240 440 Z"/>

    <!-- Forehead Region (clickable) -->
    <path class="face-region" data-region="forehead"
          d="M 80 150 Q 80 120 100 100 Q 150 70 200 70 Q 250 70 300 100 Q 320 120 320 150 L 320 200 L 80 200 Z"
          @click="$dispatch('region-clicked', { region: 'forehead' })"/>

    <!-- Glabella Region (between eyebrows) -->
    <ellipse class="face-region" data-region="glabella"
             cx="200" cy="190" rx="25" ry="20"
             @click="$dispatch('region-clicked', { region: 'glabella' })"/>

    <!-- Left Temple -->
    <ellipse class="face-region" data-region="temples-left"
             cx="90" cy="180" rx="35" ry="40"
             @click="$dispatch('region-clicked', { region: 'temples' })"/>

    <!-- Right Temple -->
    <ellipse class="face-region" data-region="temples-right"
             cx="310" cy="180" rx="35" ry="40"
             @click="$dispatch('region-clicked', { region: 'temples' })"/>

    <!-- Left Eye Structure -->
    <g>
        <!-- Upper Eyelid -->
        <path class="face-region" data-region="upper_eyelid-left"
              d="M 130 205 Q 160 195 190 205"
              @click="$dispatch('region-clicked', { region: 'upper_eyelid' })"/>

        <!-- Eye -->
        <ellipse class="eye" cx="160" cy="215" rx="22" ry="12"/>
        <circle class="pupil" cx="160" cy="215" r="8"/>
        <circle class="iris-highlight" cx="163" cy="212" r="3"/>

        <!-- Lower Eyelid -->
        <path class="face-region" data-region="lower_eyelid-left"
              d="M 130 220 Q 160 225 190 220"
              @click="$dispatch('region-clicked', { region: 'lower_eyelid' })"/>

        <!-- Crow's Feet Area -->
        <path class="face-region" data-region="crow_feet-left"
              d="M 190 205 L 210 200 L 215 210 L 210 220 L 190 220 Z"
              @click="$dispatch('region-clicked', { region: 'crow_feet' })"/>
    </g>

    <!-- Right Eye Structure -->
    <g>
        <!-- Upper Eyelid -->
        <path class="face-region" data-region="upper_eyelid-right"
              d="M 210 205 Q 240 195 270 205"
              @click="$dispatch('region-clicked', { region: 'upper_eyelid' })"/>

        <!-- Eye -->
        <ellipse class="eye" cx="240" cy="215" rx="22" ry="12"/>
        <circle class="pupil" cx="240" cy="215" r="8"/>
        <circle class="iris-highlight" cx="243" cy="212" r="3"/>

        <!-- Lower Eyelid -->
        <path class="face-region" data-region="lower_eyelid-right"
              d="M 210 220 Q 240 225 270 220"
              @click="$dispatch('region-clicked', { region: 'lower_eyelid' })"/>

        <!-- Crow's Feet Area -->
        <path class="face-region" data-region="crow_feet-right"
              d="M 210 205 L 190 200 L 185 210 L 190 220 L 210 220 Z"
              transform="translate(400, 0) scale(-1, 1)"
              @click="$dispatch('region-clicked', { region: 'crow_feet' })"/>
    </g>

    <!-- Nose Region -->
    <g>
        <path class="face-region" data-region="nose"
              d="M 185 240 L 200 280 L 215 240 Q 200 230 185 240 Z"
              @click="$dispatch('region-clicked', { region: 'nose' })"/>
        <ellipse class="feature" cx="190" cy="285" rx="8" ry="6"/>
        <ellipse class="feature" cx="210" cy="285" rx="8" ry="6"/>
    </g>

    <!-- Left Cheek -->
    <ellipse class="face-region" data-region="cheeks-left"
             cx="120" cy="280" rx="45" ry="60"
             @click="$dispatch('region-clicked', { region: 'cheeks' })"/>

    <!-- Right Cheek -->
    <ellipse class="face-region" data-region="cheeks-right"
             cx="280" cy="280" rx="45" ry="60"
             @click="$dispatch('region-clicked', { region: 'cheeks' })"/>

    <!-- Left Nasolabial Fold -->
    <path class="face-region" data-region="nasolabial-left"
          d="M 185 290 Q 150 310 140 340"
          @click="$dispatch('region-clicked', { region: 'nasolabial' })"/>

    <!-- Right Nasolabial Fold -->
    <path class="face-region" data-region="nasolabial-right"
          d="M 215 290 Q 250 310 260 340"
          @click="$dispatch('region-clicked', { region: 'nasolabial' })"/>

    <!-- Upper Lip Region -->
    <path class="face-region" data-region="upper_lip"
          d="M 170 330 Q 200 325 230 330 L 225 345 Q 200 350 175 345 Z"
          @click="$dispatch('region-clicked', { region: 'upper_lip' })"/>

    <!-- Lips -->
    <ellipse class="feature" cx="200" cy="350" rx="32" ry="10"/>

    <!-- Lower Lip Region -->
    <path class="face-region" data-region="lower_lip"
          d="M 175 355 Q 200 360 225 355 L 230 370 Q 200 375 170 370 Z"
          @click="$dispatch('region-clicked', { region: 'lower_lip' })"/>

    <!-- Left Marionette Line -->
    <path class="face-region" data-region="marionette-left"
          d="M 165 370 Q 155 385 150 400"
          @click="$dispatch('region-clicked', { region: 'marionette' })"/>

    <!-- Right Marionette Line -->
    <path class="face-region" data-region="marionette-right"
          d="M 235 370 Q 245 385 250 400"
          @click="$dispatch('region-clicked', { region: 'marionette' })"/>

    <!-- Chin Region -->
    <ellipse class="face-region" data-region="chin"
             cx="200" cy="410" rx="35" ry="30"
             @click="$dispatch('region-clicked', { region: 'chin' })"/>

    <!-- Left Jawline -->
    <path class="face-region" data-region="jawline-left"
          d="M 100 350 Q 90 380 95 410 Q 100 430 120 445"
          @click="$dispatch('region-clicked', { region: 'jawline' })"/>

    <!-- Right Jawline -->
    <path class="face-region" data-region="jawline-right"
          d="M 300 350 Q 310 380 305 410 Q 300 430 280 445"
          @click="$dispatch('region-clicked', { region: 'jawline' })"/>

    <!-- Neck Region -->
    <rect class="face-region" data-region="neck"
          x="160" y="450" width="80" height="70"
          @click="$dispatch('region-clicked', { region: 'neck' })"/>

    <!-- Muscle Definition Lines (for anatomical reference) -->
    <g class="muscle-lines">
        <!-- Frontalis muscle (forehead) -->
        <path class="muscle-line" d="M 120 130 Q 120 110 140 105"/>
        <path class="muscle-line" d="M 200 100 L 200 140"/>
        <path class="muscle-line" d="M 280 130 Q 280 110 260 105"/>

        <!-- Orbicularis oculi (around eyes) -->
        <ellipse class="muscle-line" cx="160" cy="215" rx="30" ry="18"/>
        <ellipse class="muscle-line" cx="240" cy="215" rx="30" ry="18"/>

        <!-- Nasalis muscle -->
        <path class="muscle-line" d="M 190 250 L 200 240 L 210 250"/>

        <!-- Zygomaticus major (smile lines) -->
        <path class="muscle-line" d="M 130 290 Q 170 320 185 340"/>
        <path class="muscle-line" d="M 270 290 Q 230 320 215 340"/>

        <!-- Orbicularis oris (around mouth) -->
        <ellipse class="muscle-line" cx="200" cy="350" rx="38" ry="15"/>

        <!-- Mentalis (chin) -->
        <path class="muscle-line" d="M 195 390 Q 200 400 205 390"/>

        <!-- Platysma (neck) -->
        <path class="muscle-line" d="M 175 460 Q 175 490 180 510"/>
        <path class="muscle-line" d="M 225 460 Q 225 490 220 510"/>
    </g>

    <!-- Region Labels (hidden by default, shown on hover) -->
    <text x="200" y="140" class="region-label" text-anchor="middle" fill="#666" font-size="10" opacity="0">Forehead</text>
</svg>
