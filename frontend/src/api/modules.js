export const MODULE = {
  ALLIANCE: 0,
  BUILDING: 2,
  RESEARCH: 3,
  SHIPYARD_FLEET: 4,
  SHIPYARD_DEFENSIVE: 5,
  FLEET_TABLE: 9,
  GALAXY: 11,
  TRADER: 13,
  IMPERIUM: 15,
  MESSAGES: 16,
  OFFICIER: 18,
  RECORDS: 22,
  RESOURCES: 23,
  STATISTICS: 25,
  SUPPORT: 27,
  TECHTREE: 28,
  FLEET_TRADER: 38,
  SIMULATOR: 39,
  ACHIEVEMENTS: 46,
}

export function hasModule(modules, id) {
  if (!Array.isArray(modules) || modules.length === 0) {
    return true
  }
  return modules.includes(id)
}
