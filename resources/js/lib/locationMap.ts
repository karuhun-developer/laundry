import type { LayerGroup } from 'leaflet';

export type MapCoordinates = [latitude: number, longitude: number];

export type BranchLocation = {
    readonly id: number;
    readonly name: string;
    readonly latitude: number;
    readonly longitude: number;
    readonly freeRadiusMeters: number;
    readonly selected: boolean;
};

type LocationMapOptions = {
    readonly element: HTMLDivElement;
    readonly initialCoordinates: MapCoordinates | null;
    readonly branchLocations: readonly BranchLocation[];
    readonly onCoordinatesChanged: (latitude: number, longitude: number) => void;
    readonly onBranchSelect: (branchId: number) => void;
};

export type LocationMapController = {
    readonly setCoordinates: (coordinates: MapCoordinates | null, center?: boolean) => void;
    readonly setBranchLocations: (
        branchLocations: readonly BranchLocation[],
        pickupCoordinates: MapCoordinates | null,
    ) => void;
    readonly destroy: () => void;
};

export const createLocationMap = async (
    options: LocationMapOptions,
): Promise<LocationMapController> => {
    const leaflet = await import('leaflet');
    const initialCenter: MapCoordinates = options.initialCoordinates ?? [-2.5489, 118.0149];
    const map = leaflet.map(options.element, {
        center: initialCenter,
        zoom: options.initialCoordinates ? 16 : 5,
    });
    const branchLayer: LayerGroup = leaflet.layerGroup().addTo(map);

    leaflet
        .tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        })
        .addTo(map);

    const pickupMarker = leaflet.marker(initialCenter, {
        draggable: true,
        icon: leaflet.divIcon({
            className: 'laundry-location-marker',
            html: '<span></span>',
            iconAnchor: [14, 28],
            iconSize: [28, 28],
        }),
        opacity: options.initialCoordinates ? 1 : 0,
    });

    pickupMarker.addTo(map);
    pickupMarker.on('dragend', () => {
        const point = pickupMarker.getLatLng();
        options.onCoordinatesChanged(point.lat, point.lng);
    });
    map.on('click', (event) => {
        pickupMarker.setOpacity(1);
        pickupMarker.setLatLng(event.latlng);
        options.onCoordinatesChanged(event.latlng.lat, event.latlng.lng);
    });

    const fitLocations = (
        branchLocations: readonly BranchLocation[],
        pickupCoordinates: MapCoordinates | null,
    ): void => {
        if (branchLocations.length === 0) {
            return;
        }

        const points: MapCoordinates[] = branchLocations.map((branch) => [
            branch.latitude,
            branch.longitude,
        ]);

        if (pickupCoordinates) {
            points.unshift(pickupCoordinates);
        }

        if (points.length === 1) {
            map.setView(points[0], 14);
            return;
        }

        map.fitBounds(leaflet.latLngBounds(points), { maxZoom: 14, padding: [24, 24] });
    };

    const renderBranchLocations = (branchLocations: readonly BranchLocation[]): void => {
        branchLayer.clearLayers();

        for (const branch of branchLocations) {
            const selectBranch = (): void => options.onBranchSelect(branch.id);
            const coverageCircle = leaflet.circle([branch.latitude, branch.longitude], {
                bubblingMouseEvents: false,
                color: branch.selected ? '#1559c7' : '#69c3f6',
                fillColor: branch.selected ? '#1559c7' : '#69c3f6',
                fillOpacity: branch.selected ? 0.12 : 0.07,
                radius: branch.freeRadiusMeters,
                weight: branch.selected ? 2 : 1,
            });
            const branchMarker = leaflet.marker([branch.latitude, branch.longitude], {
                icon: leaflet.divIcon({
                    className: branch.selected
                        ? 'laundry-branch-marker laundry-branch-marker--selected'
                        : 'laundry-branch-marker',
                    html: '<span></span>',
                    iconAnchor: [12, 12],
                    iconSize: [24, 24],
                }),
                keyboard: true,
                title: branch.name,
            });
            const tooltip = document.createElement('span');

            tooltip.textContent = branch.name;
            coverageCircle.on('click', selectBranch);
            branchMarker.on('click', selectBranch);
            branchMarker.bindTooltip(tooltip, { direction: 'top', offset: [0, -14] });
            coverageCircle.addTo(branchLayer);
            branchMarker.addTo(branchLayer);
        }
    };

    const setCoordinates = (coordinates: MapCoordinates | null, center = false): void => {
        if (!coordinates) {
            pickupMarker.setOpacity(0);
            return;
        }

        pickupMarker.setOpacity(1);
        pickupMarker.setLatLng(coordinates);

        if (center) {
            map.setView(coordinates, 16);
        }
    };

    const setBranchLocations = (
        branchLocations: readonly BranchLocation[],
        pickupCoordinates: MapCoordinates | null,
    ): void => {
        renderBranchLocations(branchLocations);
        fitLocations(branchLocations, pickupCoordinates);
    };

    setBranchLocations(options.branchLocations, options.initialCoordinates);
    window.setTimeout(() => map.invalidateSize(), 0);

    return {
        setCoordinates,
        setBranchLocations,
        destroy: () => {
            branchLayer.clearLayers();
            map.remove();
        },
    };
};
