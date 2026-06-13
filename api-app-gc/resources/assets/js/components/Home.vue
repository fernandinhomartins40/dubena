<template>
    <div class="wrap-banner">
        <vue-particles
                :particleOpacity="0.7"
                :particlesNumber="120"
                shapeType="polygon"
                :particleSize="2"
                linesColor="#868686"
                color="#868686"
                :linesWidth="1"
                :lineLinked="true"
                :lineOpacity="0.6"
                :linesDistance="85"
                :moveSpeed="2"
                :hoverEffect="true"
                hoverMode="grab"
                :clickEffect="true"
                clickMode="repulse"
        >
        </vue-particles>
    </div>
</template>

<style scoped>
    #particles-js {
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
</style>