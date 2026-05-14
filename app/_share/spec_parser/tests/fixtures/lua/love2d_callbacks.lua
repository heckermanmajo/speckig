-- @spec
-- Love2D demo entry: load assets, advance position, draw the world, react to input.
-- @end-spec

-- @spec
-- Engine entry point. Loads the player sprite once at startup.
-- called by Love2D before the first frame
-- @end-spec
function love.load()
    player = { x = 0, y = 0, vx = 50 }
end

-- @spec
-- Advance simulation by dt seconds.
-- dt is always > 0 (Love2D guarantees this)
-- @end-spec
function love.update(dt)
    player.x = player.x + player.vx * dt
end

-- @spec
-- Draw the player sprite at its current position.
-- @end-spec
function love.draw()
    love.graphics.rectangle("fill", player.x, player.y, 10, 10)
end

-- @spec
-- React to keyboard input; "escape" quits the game.
-- @end-spec
function love.keypressed(key)
    if key == "escape" then
        love.event.quit()
    end
end
