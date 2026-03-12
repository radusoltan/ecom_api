<?php

declare(strict_types=1);

namespace App\DataFixtures;

final class Sprint3SeedData
{
    public const LOCALES = ['en', 'fr', 'de'];
    public const PRODUCTS_PER_LEAF = 28;
    public const CONFIGURABLE_PRODUCTS_PER_LEAF = 5;
    public const CUSTOMERS_PER_TENANT = 72;
    public const ORDERS_PER_TENANT = 170;
    public const INVOICES_PER_TENANT = 36;
    public const PROMOTIONS_PER_TENANT = 18;
    public const WAREHOUSES_PER_TENANT = 2;
    public const VARIANTS_PER_CONFIGURABLE = 6;

    public const ORDER_STATUS_DISTRIBUTION = [
        'pending' => 34,
        'processing' => 34,
        'shipped' => 34,
        'delivered' => 34,
        'cancelled' => 34,
    ];

    /**
     * @return array<string, array{
     *     alias: string,
     *     code: string,
     *     name: string,
     *     ownerEmail: string,
     *     adminEmail: string,
     *     staffEmail: string,
     *     customerDomain: string,
     *     profile: string,
     *     brands: array<int, string>,
     *     series: array<int, string>,
     *     categories: array<int, array{
     *         key: string,
     *         name: array<string, string>,
     *         description: array<string, string>,
     *         children: array<int, array{
     *             key: string,
     *             leafCode: string,
     *             label: array<string, string>,
     *             name: array<string, string>,
     *             description: array<string, string>
     *         }>
     *     }>
     * }>
     */
    public static function tenants(): array
    {
        return [
            'techmart' => [
                'alias' => 'techmart',
                'code' => 'TEC',
                'name' => 'TechMart',
                'ownerEmail' => 'owner@techmart.com',
                'adminEmail' => 'admin@techmart.com',
                'staffEmail' => 'staff@techmart.com',
                'customerDomain' => 'techmart.seed.test',
                'profile' => 'electronics',
                'brands' => ['Apex', 'Nimbus', 'Northstar', 'Volt', 'Axis', 'Helio'],
                'series' => ['Pulse', 'Summit', 'Vertex', 'Orbit', 'Signal', 'Nova'],
                'categories' => [
                    [
                        'key' => 'devices',
                        'name' => ['en' => 'TechMart Devices', 'fr' => 'Appareils TechMart', 'de' => 'TechMart Geraete'],
                        'description' => ['en' => 'Connected devices for work and daily life.', 'fr' => 'Des appareils connectes pour le travail et le quotidien.', 'de' => 'Vernetzte Geraete fuer Arbeit und Alltag.'],
                        'children' => [
                            [
                                'key' => 'laptops',
                                'leafCode' => 'LAP',
                                'label' => ['en' => 'Laptop', 'fr' => 'Ordinateur portable', 'de' => 'Laptop'],
                                'name' => ['en' => 'TechMart Laptops', 'fr' => 'Ordinateurs portables TechMart', 'de' => 'TechMart Laptops'],
                                'description' => ['en' => 'Portable computers for mobile teams and creators.', 'fr' => 'Des ordinateurs portables pour les equipes mobiles et les createurs.', 'de' => 'Mobile Computer fuer Teams und Kreative.'],
                            ],
                            [
                                'key' => 'tablets',
                                'leafCode' => 'TAB',
                                'label' => ['en' => 'Tablet', 'fr' => 'Tablette', 'de' => 'Tablet'],
                                'name' => ['en' => 'TechMart Tablets', 'fr' => 'Tablettes TechMart', 'de' => 'TechMart Tablets'],
                                'description' => ['en' => 'Tablets built for travel, note taking, and content streaming.', 'fr' => 'Des tablettes concues pour le voyage, la prise de notes et le streaming.', 'de' => 'Tablets fuer Reisen, Notizen und Streaming.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'mobile',
                        'name' => ['en' => 'TechMart Mobile', 'fr' => 'Mobile TechMart', 'de' => 'TechMart Mobile'],
                        'description' => ['en' => 'Everyday communication and mobile productivity.', 'fr' => 'Communication quotidienne et productivite mobile.', 'de' => 'Alltaegliche Kommunikation und mobile Produktivitaet.'],
                        'children' => [
                            [
                                'key' => 'smartphones',
                                'leafCode' => 'PHN',
                                'label' => ['en' => 'Smartphone', 'fr' => 'Smartphone', 'de' => 'Smartphone'],
                                'name' => ['en' => 'TechMart Smartphones', 'fr' => 'Smartphones TechMart', 'de' => 'TechMart Smartphones'],
                                'description' => ['en' => 'Smartphones with modern cameras and responsive displays.', 'fr' => 'Des smartphones avec des appareils photo modernes et des ecrans reactifs.', 'de' => 'Smartphones mit modernen Kameras und reaktionsschnellen Displays.'],
                            ],
                            [
                                'key' => 'wearables',
                                'leafCode' => 'WRB',
                                'label' => ['en' => 'Wearable', 'fr' => 'Objet connecte', 'de' => 'Wearable'],
                                'name' => ['en' => 'TechMart Wearables', 'fr' => 'Objets connectes TechMart', 'de' => 'TechMart Wearables'],
                                'description' => ['en' => 'Wearables for wellness, alerts, and movement tracking.', 'fr' => 'Des objets connectes pour le bien-etre, les alertes et le suivi des mouvements.', 'de' => 'Wearables fuer Wellness, Benachrichtigungen und Bewegungsdaten.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'audio',
                        'name' => ['en' => 'TechMart Audio', 'fr' => 'Audio TechMart', 'de' => 'TechMart Audio'],
                        'description' => ['en' => 'Immersive sound for home offices and media rooms.', 'fr' => 'Un son immersif pour les bureaux a domicile et les espaces media.', 'de' => 'Immersiver Klang fuer Homeoffice und Medienraeume.'],
                        'children' => [
                            [
                                'key' => 'headphones',
                                'leafCode' => 'HDP',
                                'label' => ['en' => 'Headphones', 'fr' => 'Casque audio', 'de' => 'Kopfhoerer'],
                                'name' => ['en' => 'TechMart Headphones', 'fr' => 'Casques audio TechMart', 'de' => 'TechMart Kopfhoerer'],
                                'description' => ['en' => 'Headphones for commuting, gaming, and studio sessions.', 'fr' => 'Des casques audio pour les trajets, le jeu et le studio.', 'de' => 'Kopfhoerer fuer Pendeln, Gaming und Studioarbeit.'],
                            ],
                            [
                                'key' => 'speakers',
                                'leafCode' => 'SPK',
                                'label' => ['en' => 'Speaker', 'fr' => 'Enceinte', 'de' => 'Lautsprecher'],
                                'name' => ['en' => 'TechMart Speakers', 'fr' => 'Enceintes TechMart', 'de' => 'TechMart Lautsprecher'],
                                'description' => ['en' => 'Portable and room scale speakers with clear low end.', 'fr' => 'Des enceintes portables et domestiques avec des basses nettes.', 'de' => 'Portable und raumfuellende Lautsprecher mit klarem Bass.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'imaging',
                        'name' => ['en' => 'TechMart Imaging', 'fr' => 'Imagerie TechMart', 'de' => 'TechMart Imaging'],
                        'description' => ['en' => 'Capture gear for creators and adventure teams.', 'fr' => 'Du materiel de capture pour les createurs et les equipes terrain.', 'de' => 'Aufnahmetechnik fuer Kreative und mobile Teams.'],
                        'children' => [
                            [
                                'key' => 'cameras',
                                'leafCode' => 'CAM',
                                'label' => ['en' => 'Camera', 'fr' => 'Appareil photo', 'de' => 'Kamera'],
                                'name' => ['en' => 'TechMart Cameras', 'fr' => 'Appareils photo TechMart', 'de' => 'TechMart Kameras'],
                                'description' => ['en' => 'Cameras for travel journals, portraits, and production work.', 'fr' => 'Des appareils photo pour les voyages, le portrait et la production.', 'de' => 'Kameras fuer Reisen, Portraits und Produktionen.'],
                            ],
                            [
                                'key' => 'drones',
                                'leafCode' => 'DRN',
                                'label' => ['en' => 'Drone', 'fr' => 'Drone', 'de' => 'Drohne'],
                                'name' => ['en' => 'TechMart Drones', 'fr' => 'Drones TechMart', 'de' => 'TechMart Drohnen'],
                                'description' => ['en' => 'Aerial drones with stabilized footage and compact travel kits.', 'fr' => 'Des drones aeriens avec stabilisation et kits compacts.', 'de' => 'Drohnen mit stabilisierten Aufnahmen und kompakten Sets.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'office',
                        'name' => ['en' => 'TechMart Office', 'fr' => 'Bureau TechMart', 'de' => 'TechMart Office'],
                        'description' => ['en' => 'Equipment for focused desk setups and hybrid teams.', 'fr' => 'Des equipements pour les postes de travail et les equipes hybrides.', 'de' => 'Ausstattung fuer fokussierte Arbeitsplaetze und hybride Teams.'],
                        'children' => [
                            [
                                'key' => 'monitors',
                                'leafCode' => 'MON',
                                'label' => ['en' => 'Monitor', 'fr' => 'Moniteur', 'de' => 'Monitor'],
                                'name' => ['en' => 'TechMart Monitors', 'fr' => 'Moniteurs TechMart', 'de' => 'TechMart Monitore'],
                                'description' => ['en' => 'Desktop displays tuned for sharp text and balanced color.', 'fr' => 'Des ecrans de bureau avec un texte net et des couleurs equilibrees.', 'de' => 'Desktop-Displays mit scharfem Text und ausgewogenen Farben.'],
                            ],
                            [
                                'key' => 'printers',
                                'leafCode' => 'PRN',
                                'label' => ['en' => 'Printer', 'fr' => 'Imprimante', 'de' => 'Drucker'],
                                'name' => ['en' => 'TechMart Printers', 'fr' => 'Imprimantes TechMart', 'de' => 'TechMart Drucker'],
                                'description' => ['en' => 'Reliable printers for invoices, labels, and team handouts.', 'fr' => 'Des imprimantes fiables pour les factures, etiquettes et supports.', 'de' => 'Zuverlaessige Drucker fuer Rechnungen, Labels und Unterlagen.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'network',
                        'name' => ['en' => 'TechMart Network', 'fr' => 'Reseau TechMart', 'de' => 'TechMart Netzwerk'],
                        'description' => ['en' => 'Connectivity and storage essentials for growing operations.', 'fr' => 'Les essentiels de connectivite et de stockage pour les operations en croissance.', 'de' => 'Konnektivitaets- und Speicherloesungen fuer wachsende Teams.'],
                        'children' => [
                            [
                                'key' => 'routers',
                                'leafCode' => 'RTR',
                                'label' => ['en' => 'Router', 'fr' => 'Routeur', 'de' => 'Router'],
                                'name' => ['en' => 'TechMart Routers', 'fr' => 'Routeurs TechMart', 'de' => 'TechMart Router'],
                                'description' => ['en' => 'Routers and mesh systems for stable office coverage.', 'fr' => 'Des routeurs et reseaux mailles pour une couverture stable.', 'de' => 'Router und Mesh-Systeme fuer stabile Abdeckung.'],
                            ],
                            [
                                'key' => 'storage',
                                'leafCode' => 'STO',
                                'label' => ['en' => 'Storage Unit', 'fr' => 'Unite de stockage', 'de' => 'Speichergeraet'],
                                'name' => ['en' => 'TechMart Storage', 'fr' => 'Stockage TechMart', 'de' => 'TechMart Speicher'],
                                'description' => ['en' => 'Fast storage for archives, transfers, and media workflows.', 'fr' => 'Un stockage rapide pour les archives, transferts et medias.', 'de' => 'Schneller Speicher fuer Archive, Transfers und Medien-Workflows.'],
                            ],
                        ],
                    ],
                ],
            ],
            'fashion_hub' => [
                'alias' => 'fashion_hub',
                'code' => 'FSH',
                'name' => 'Fashion Hub',
                'ownerEmail' => 'owner@fashionhub.com',
                'adminEmail' => 'admin@fashionhub.com',
                'staffEmail' => 'stylist@fashionhub.com',
                'customerDomain' => 'fashionhub.seed.test',
                'profile' => 'fashion',
                'brands' => ['Maison Rue', 'Atelier Nine', 'Luma', 'Cedar Lane', 'Rive', 'North Finch'],
                'series' => ['Studio', 'Weekend', 'Edition', 'Signature', 'Season', 'Capsule'],
                'categories' => [
                    [
                        'key' => 'womenswear',
                        'name' => ['en' => 'Fashion Hub Womenswear', 'fr' => 'Mode femme Fashion Hub', 'de' => 'Fashion Hub Damenmode'],
                        'description' => ['en' => 'Modern womenswear with soft tailoring and fluid layers.', 'fr' => 'Une mode femme moderne avec des coupes souples et des couches fluides.', 'de' => 'Moderne Damenmode mit weichen Schnitten und fliessenden Lagen.'],
                        'children' => [
                            [
                                'key' => 'dresses',
                                'leafCode' => 'DRS',
                                'label' => ['en' => 'Dress', 'fr' => 'Robe', 'de' => 'Kleid'],
                                'name' => ['en' => 'Fashion Hub Dresses', 'fr' => 'Robes Fashion Hub', 'de' => 'Fashion Hub Kleider'],
                                'description' => ['en' => 'Dresses for work events, weekend plans, and occasion edits.', 'fr' => 'Des robes pour le bureau, le week-end et les occasions.', 'de' => 'Kleider fuer Arbeit, Wochenende und besondere Anlaesse.'],
                            ],
                            [
                                'key' => 'knitwear',
                                'leafCode' => 'KNT',
                                'label' => ['en' => 'Knitwear', 'fr' => 'Maille', 'de' => 'Strickmode'],
                                'name' => ['en' => 'Fashion Hub Knitwear', 'fr' => 'Maille Fashion Hub', 'de' => 'Fashion Hub Strickmode'],
                                'description' => ['en' => 'Layering knitwear with soft hand feel and easy movement.', 'fr' => 'Des pieces en maille avec un toucher doux et une belle aisance.', 'de' => 'Strickteile mit weichem Griff und angenehmer Bewegungsfreiheit.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'menswear',
                        'name' => ['en' => 'Fashion Hub Menswear', 'fr' => 'Mode homme Fashion Hub', 'de' => 'Fashion Hub Herrenmode'],
                        'description' => ['en' => 'Menswear built around fit, texture, and repeat wear.', 'fr' => 'Une mode homme axee sur la coupe, la texture et le quotidien.', 'de' => 'Herrenmode mit Fokus auf Passform, Textur und Alltag.'],
                        'children' => [
                            [
                                'key' => 'tailoring',
                                'leafCode' => 'TLR',
                                'label' => ['en' => 'Tailored Jacket', 'fr' => 'Veste taillee', 'de' => 'Sakko'],
                                'name' => ['en' => 'Fashion Hub Tailoring', 'fr' => 'Tailoring Fashion Hub', 'de' => 'Fashion Hub Tailoring'],
                                'description' => ['en' => 'Tailoring essentials with sharp shoulders and easy drape.', 'fr' => 'Les essentiels du tailoring avec de belles epaules et un drape souple.', 'de' => 'Tailoring-Basics mit klarer Schulter und leichtem Fall.'],
                            ],
                            [
                                'key' => 'casualwear',
                                'leafCode' => 'CSL',
                                'label' => ['en' => 'Casual Shirt', 'fr' => 'Chemise casual', 'de' => 'Freizeithemd'],
                                'name' => ['en' => 'Fashion Hub Casualwear', 'fr' => 'Casualwear Fashion Hub', 'de' => 'Fashion Hub Casualwear'],
                                'description' => ['en' => 'Relaxed shirts and overshirts for off duty outfits.', 'fr' => 'Des chemises decontractees et surchemises pour les looks du quotidien.', 'de' => 'Lockere Hemden und Overshirts fuer entspannte Outfits.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'footwear',
                        'name' => ['en' => 'Fashion Hub Footwear', 'fr' => 'Chaussures Fashion Hub', 'de' => 'Fashion Hub Schuhe'],
                        'description' => ['en' => 'Footwear that balances comfort, finish, and daily mileage.', 'fr' => 'Des chaussures qui equilibrent confort, finition et usage quotidien.', 'de' => 'Schuhe mit Komfort, guter Verarbeitung und Alltagstauglichkeit.'],
                        'children' => [
                            [
                                'key' => 'sneakers',
                                'leafCode' => 'SNK',
                                'label' => ['en' => 'Sneaker', 'fr' => 'Sneaker', 'de' => 'Sneaker'],
                                'name' => ['en' => 'Fashion Hub Sneakers', 'fr' => 'Sneakers Fashion Hub', 'de' => 'Fashion Hub Sneaker'],
                                'description' => ['en' => 'Clean sneakers for all day wear and weekend styling.', 'fr' => 'Des sneakers sobres pour toute la journee et le week-end.', 'de' => 'Klare Sneaker fuer den ganzen Tag und das Wochenende.'],
                            ],
                            [
                                'key' => 'boots',
                                'leafCode' => 'BOT',
                                'label' => ['en' => 'Boot', 'fr' => 'Botte', 'de' => 'Boot'],
                                'name' => ['en' => 'Fashion Hub Boots', 'fr' => 'Bottes Fashion Hub', 'de' => 'Fashion Hub Boots'],
                                'description' => ['en' => 'Boots with structured uppers and city-ready traction.', 'fr' => 'Des bottes avec des tiges structurees et une bonne adherence urbaine.', 'de' => 'Boots mit stabilen Schaeften und Grip fuer die Stadt.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'accessories',
                        'name' => ['en' => 'Fashion Hub Accessories', 'fr' => 'Accessoires Fashion Hub', 'de' => 'Fashion Hub Accessoires'],
                        'description' => ['en' => 'Accessories that finish outfits without overworking them.', 'fr' => 'Des accessoires qui finalisent les silhouettes sans les surcharger.', 'de' => 'Accessoires, die Outfits klar abrunden.'],
                        'children' => [
                            [
                                'key' => 'bags',
                                'leafCode' => 'BAG',
                                'label' => ['en' => 'Bag', 'fr' => 'Sac', 'de' => 'Tasche'],
                                'name' => ['en' => 'Fashion Hub Bags', 'fr' => 'Sacs Fashion Hub', 'de' => 'Fashion Hub Taschen'],
                                'description' => ['en' => 'Bags for commutes, travel edits, and polished day wear.', 'fr' => 'Des sacs pour les trajets, les voyages et les looks soignes.', 'de' => 'Taschen fuer Pendeln, Reisen und gepflegte Tageslooks.'],
                            ],
                            [
                                'key' => 'jewelry',
                                'leafCode' => 'JWL',
                                'label' => ['en' => 'Jewelry Piece', 'fr' => 'Bijou', 'de' => 'Schmuckstueck'],
                                'name' => ['en' => 'Fashion Hub Jewelry', 'fr' => 'Bijoux Fashion Hub', 'de' => 'Fashion Hub Schmuck'],
                                'description' => ['en' => 'Layerable jewelry with warm finishes and clean lines.', 'fr' => 'Des bijoux superposables avec des finitions chaleureuses et nettes.', 'de' => 'Kombinierbarer Schmuck mit warmen Oberflaechen und klaren Linien.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'active',
                        'name' => ['en' => 'Fashion Hub Active', 'fr' => 'Active Fashion Hub', 'de' => 'Fashion Hub Active'],
                        'description' => ['en' => 'Movement-ready pieces with breathable fabrication.', 'fr' => 'Des pieces pretes pour le mouvement avec des matieres respirantes.', 'de' => 'Bewegungsfreundliche Teile mit atmungsaktiven Stoffen.'],
                        'children' => [
                            [
                                'key' => 'leggings',
                                'leafCode' => 'LEG',
                                'label' => ['en' => 'Leggings', 'fr' => 'Legging', 'de' => 'Leggings'],
                                'name' => ['en' => 'Fashion Hub Leggings', 'fr' => 'Leggings Fashion Hub', 'de' => 'Fashion Hub Leggings'],
                                'description' => ['en' => 'High stretch leggings for studio sessions and travel days.', 'fr' => 'Des leggings tres extensibles pour le studio et les deplacements.', 'de' => 'Dehnbare Leggings fuer Studio, Reisen und Training.'],
                            ],
                            [
                                'key' => 'outerwear',
                                'leafCode' => 'OTR',
                                'label' => ['en' => 'Light Jacket', 'fr' => 'Veste legere', 'de' => 'Leichte Jacke'],
                                'name' => ['en' => 'Fashion Hub Outerwear', 'fr' => 'Outerwear Fashion Hub', 'de' => 'Fashion Hub Outerwear'],
                                'description' => ['en' => 'Lightweight layers for training warmups and city errands.', 'fr' => 'Des couches legeres pour l echauffement et les sorties urbaines.', 'de' => 'Leichte Lagen fuer Training und Erledigungen in der Stadt.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'kids',
                        'name' => ['en' => 'Fashion Hub Kids', 'fr' => 'Enfant Fashion Hub', 'de' => 'Fashion Hub Kids'],
                        'description' => ['en' => 'Play-ready outfits with durable trims and soft comfort.', 'fr' => 'Des tenues prêtes a jouer avec des finitions solides et du confort.', 'de' => 'Outfits fuer Kinder mit robusten Details und weichem Komfort.'],
                        'children' => [
                            [
                                'key' => 'kids_sets',
                                'leafCode' => 'KST',
                                'label' => ['en' => 'Kids Set', 'fr' => 'Ensemble enfant', 'de' => 'Kinder-Set'],
                                'name' => ['en' => 'Fashion Hub Kids Sets', 'fr' => 'Ensembles enfant Fashion Hub', 'de' => 'Fashion Hub Kinder-Sets'],
                                'description' => ['en' => 'Matching kids sets made for repeat washes and easy mornings.', 'fr' => 'Des ensembles enfant pour les lavages frequents et les matins faciles.', 'de' => 'Kinder-Sets fuer haeufiges Waschen und entspannte Morgen.'],
                            ],
                            [
                                'key' => 'mini_jackets',
                                'leafCode' => 'MJK',
                                'label' => ['en' => 'Mini Jacket', 'fr' => 'Mini veste', 'de' => 'Mini-Jacke'],
                                'name' => ['en' => 'Fashion Hub Mini Jackets', 'fr' => 'Mini vestes Fashion Hub', 'de' => 'Fashion Hub Mini-Jacken'],
                                'description' => ['en' => 'Mini jackets for layering over school and weekend looks.', 'fr' => 'Des mini vestes a superposer sur les looks d ecole et du week-end.', 'de' => 'Mini-Jacken fuer Schule und Wochenende.'],
                            ],
                        ],
                    ],
                ],
            ],
            'homegoods' => [
                'alias' => 'homegoods',
                'code' => 'HGP',
                'name' => 'HomeGoods Plus',
                'ownerEmail' => 'owner@homegoods.com',
                'adminEmail' => 'admin@homegoods.com',
                'staffEmail' => 'staff@homegoods.com',
                'customerDomain' => 'homegoods.seed.test',
                'profile' => 'home',
                'brands' => ['Oakwell', 'Northroom', 'Harbor & Pine', 'Linen House', 'Wren', 'Atelier Home'],
                'series' => ['Residence', 'Foundry', 'Gallery', 'Everyday', 'Signature', 'Studio'],
                'categories' => [
                    [
                        'key' => 'living_room',
                        'name' => ['en' => 'HomeGoods Living Room', 'fr' => 'Salon HomeGoods', 'de' => 'HomeGoods Wohnzimmer'],
                        'description' => ['en' => 'Comfort-led living room pieces with durable finishes.', 'fr' => 'Des pieces de salon confortables avec des finitions durables.', 'de' => 'Wohnzimmermoebel mit Komfort und langlebigen Oberflaechen.'],
                        'children' => [
                            [
                                'key' => 'sofas',
                                'leafCode' => 'SOF',
                                'label' => ['en' => 'Sofa', 'fr' => 'Canape', 'de' => 'Sofa'],
                                'name' => ['en' => 'HomeGoods Sofas', 'fr' => 'Canapes HomeGoods', 'de' => 'HomeGoods Sofas'],
                                'description' => ['en' => 'Sofas with balanced proportions and everyday resilience.', 'fr' => 'Des canapes aux belles proportions et a la resistance quotidienne.', 'de' => 'Sofas mit ausgewogenen Proportionen und Alltagstauglichkeit.'],
                            ],
                            [
                                'key' => 'coffee_tables',
                                'leafCode' => 'CFT',
                                'label' => ['en' => 'Coffee Table', 'fr' => 'Table basse', 'de' => 'Couchtisch'],
                                'name' => ['en' => 'HomeGoods Coffee Tables', 'fr' => 'Tables basses HomeGoods', 'de' => 'HomeGoods Couchtische'],
                                'description' => ['en' => 'Coffee tables designed for compact rooms and open lounges.', 'fr' => 'Des tables basses pour les pieces compactes et les grands salons.', 'de' => 'Couchtische fuer kompakte Raeume und offene Wohnbereiche.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'bedroom',
                        'name' => ['en' => 'HomeGoods Bedroom', 'fr' => 'Chambre HomeGoods', 'de' => 'HomeGoods Schlafzimmer'],
                        'description' => ['en' => 'Bedroom furniture that layers storage with calm styling.', 'fr' => 'Du mobilier de chambre qui melange rangement et douceur visuelle.', 'de' => 'Schlafzimmermoebel mit Ruhe, Stil und Stauraum.'],
                        'children' => [
                            [
                                'key' => 'beds',
                                'leafCode' => 'BED',
                                'label' => ['en' => 'Bed Frame', 'fr' => 'Cadre de lit', 'de' => 'Bettgestell'],
                                'name' => ['en' => 'HomeGoods Beds', 'fr' => 'Lits HomeGoods', 'de' => 'HomeGoods Betten'],
                                'description' => ['en' => 'Bed frames with stable platforms and soft detailing.', 'fr' => 'Des cadres de lit stables avec des details doux.', 'de' => 'Bettgestelle mit stabilen Plattformen und weichen Details.'],
                            ],
                            [
                                'key' => 'wardrobes',
                                'leafCode' => 'WRD',
                                'label' => ['en' => 'Wardrobe', 'fr' => 'Armoire', 'de' => 'Kleiderschrank'],
                                'name' => ['en' => 'HomeGoods Wardrobes', 'fr' => 'Armoires HomeGoods', 'de' => 'HomeGoods Kleiderschraenke'],
                                'description' => ['en' => 'Wardrobes with modular storage for apartments and family homes.', 'fr' => 'Des armoires modulaires pour appartements et maisons familiales.', 'de' => 'Modulare Kleiderschraenke fuer Wohnungen und Familienhaeuser.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'dining',
                        'name' => ['en' => 'HomeGoods Dining', 'fr' => 'Salle a manger HomeGoods', 'de' => 'HomeGoods Essbereich'],
                        'description' => ['en' => 'Dining essentials for hosting, meal prep, and daily routines.', 'fr' => 'Des essentiels pour recevoir, cuisiner et les routines quotidiennes.', 'de' => 'Essbereich-Produkte fuer Hosting, Kochen und Alltag.'],
                        'children' => [
                            [
                                'key' => 'dining_chairs',
                                'leafCode' => 'CHR',
                                'label' => ['en' => 'Dining Chair', 'fr' => 'Chaise de salle a manger', 'de' => 'Esszimmerstuhl'],
                                'name' => ['en' => 'HomeGoods Dining Chairs', 'fr' => 'Chaises HomeGoods', 'de' => 'HomeGoods Esszimmerstuehle'],
                                'description' => ['en' => 'Dining chairs with supportive backs and easy-clean fabrics.', 'fr' => 'Des chaises avec bon maintien et tissus faciles a entretenir.', 'de' => 'Esszimmerstuehle mit gutem Halt und pflegeleichten Stoffen.'],
                            ],
                            [
                                'key' => 'tableware',
                                'leafCode' => 'TBW',
                                'label' => ['en' => 'Tableware Set', 'fr' => 'Service de table', 'de' => 'Tafelservice'],
                                'name' => ['en' => 'HomeGoods Tableware', 'fr' => 'Art de la table HomeGoods', 'de' => 'HomeGoods Tafelservice'],
                                'description' => ['en' => 'Tableware sets for weeknight dinners and hosted gatherings.', 'fr' => 'Des services de table pour les diners quotidiens et les receptions.', 'de' => 'Tafelservice fuer Wochentage und Einladungen.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'kitchen',
                        'name' => ['en' => 'HomeGoods Kitchen', 'fr' => 'Cuisine HomeGoods', 'de' => 'HomeGoods Kueche'],
                        'description' => ['en' => 'Kitchen upgrades that simplify prep and tidy storage.', 'fr' => 'Des upgrades de cuisine pour simplifier la preparation et le rangement.', 'de' => 'Kuechenprodukte fuer einfache Vorbereitung und Ordnung.'],
                        'children' => [
                            [
                                'key' => 'cookware',
                                'leafCode' => 'CKW',
                                'label' => ['en' => 'Cookware Set', 'fr' => 'Batterie de cuisine', 'de' => 'Kochgeschirr-Set'],
                                'name' => ['en' => 'HomeGoods Cookware', 'fr' => 'Cookware HomeGoods', 'de' => 'HomeGoods Kochgeschirr'],
                                'description' => ['en' => 'Cookware sets with balanced heat response and easy cleanup.', 'fr' => 'Des batteries de cuisine avec chauffe reguliere et nettoyage facile.', 'de' => 'Kochgeschirr-Sets mit gleichmaessiger Hitze und einfacher Reinigung.'],
                            ],
                            [
                                'key' => 'small_appliances',
                                'leafCode' => 'APP',
                                'label' => ['en' => 'Small Appliance', 'fr' => 'Petit appareil', 'de' => 'Kleingeraet'],
                                'name' => ['en' => 'HomeGoods Small Appliances', 'fr' => 'Petits appareils HomeGoods', 'de' => 'HomeGoods Kleingeraete'],
                                'description' => ['en' => 'Countertop appliances made for steady daily use.', 'fr' => 'Des appareils de plan de travail pour un usage quotidien soutenu.', 'de' => 'Kleingeraete fuer den zuverlaessigen taeglichen Einsatz.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'outdoor',
                        'name' => ['en' => 'HomeGoods Outdoor', 'fr' => 'Exterieur HomeGoods', 'de' => 'HomeGoods Outdoor'],
                        'description' => ['en' => 'Outdoor living sets for patios, balconies, and garden corners.', 'fr' => 'Des ensembles exterieurs pour terrasses, balcons et jardins.', 'de' => 'Outdoor-Sets fuer Terrasse, Balkon und Garten.'],
                        'children' => [
                            [
                                'key' => 'patio_sets',
                                'leafCode' => 'PAT',
                                'label' => ['en' => 'Patio Set', 'fr' => 'Ensemble patio', 'de' => 'Patio-Set'],
                                'name' => ['en' => 'HomeGoods Patio Sets', 'fr' => 'Ensembles patio HomeGoods', 'de' => 'HomeGoods Patio-Sets'],
                                'description' => ['en' => 'Patio sets built for hosting under changing weather.', 'fr' => 'Des ensembles patio concus pour recevoir par temps changeant.', 'de' => 'Patio-Sets fuer Gaeste und wechselndes Wetter.'],
                            ],
                            [
                                'key' => 'garden_lighting',
                                'leafCode' => 'LGT',
                                'label' => ['en' => 'Garden Light', 'fr' => 'Lumiere de jardin', 'de' => 'Gartenleuchte'],
                                'name' => ['en' => 'HomeGoods Garden Lighting', 'fr' => 'Luminaires de jardin HomeGoods', 'de' => 'HomeGoods Gartenleuchten'],
                                'description' => ['en' => 'Garden lights with warm glow and weather-ready finishes.', 'fr' => 'Des luminaires de jardin avec une lumiere chaude et des finitions resistantes.', 'de' => 'Gartenleuchten mit warmem Licht und wetterfesten Oberflaechen.'],
                            ],
                        ],
                    ],
                    [
                        'key' => 'decor',
                        'name' => ['en' => 'HomeGoods Decor', 'fr' => 'Decoration HomeGoods', 'de' => 'HomeGoods Dekor'],
                        'description' => ['en' => 'Decor pieces that bring texture and warmth into every room.', 'fr' => 'Des pieces deco qui apportent texture et chaleur a chaque piece.', 'de' => 'Dekor, das Textur und Waerme in jeden Raum bringt.'],
                        'children' => [
                            [
                                'key' => 'wall_art',
                                'leafCode' => 'ART',
                                'label' => ['en' => 'Wall Art', 'fr' => 'Art mural', 'de' => 'Wandkunst'],
                                'name' => ['en' => 'HomeGoods Wall Art', 'fr' => 'Art mural HomeGoods', 'de' => 'HomeGoods Wandkunst'],
                                'description' => ['en' => 'Wall art collections for calm interiors and gallery walls.', 'fr' => 'Des collections murales pour des interieurs calmes et des murs galerie.', 'de' => 'Wandkunst fuer ruhige Innenraeume und Gallery Walls.'],
                            ],
                            [
                                'key' => 'textiles',
                                'leafCode' => 'TXT',
                                'label' => ['en' => 'Textile Accent', 'fr' => 'Textile deco', 'de' => 'Textil-Akzent'],
                                'name' => ['en' => 'HomeGoods Textiles', 'fr' => 'Textiles HomeGoods', 'de' => 'HomeGoods Textilien'],
                                'description' => ['en' => 'Throws, cushions, and layered textiles for softer rooms.', 'fr' => 'Des plaids, coussins et textiles pour adoucir les pieces.', 'de' => 'Plaids, Kissen und Textilien fuer weichere Raeume.'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     code: array{nameTranslations: array<string, string>, values: array<int, array{code: string, nameTranslations: array<string, string>}>},
     *     secondary: array{nameTranslations: array<string, string>, values: array<int, array{code: string, nameTranslations: array<string, string>}>}
     * }
     */
    public static function optionProfile(string $profile): array
    {
        return match ($profile) {
            'electronics' => [
                'code' => [
                    'nameTranslations' => ['en' => 'Color', 'fr' => 'Couleur', 'de' => 'Farbe'],
                    'values' => [
                        ['code' => 'black', 'nameTranslations' => ['en' => 'Black', 'fr' => 'Noir', 'de' => 'Schwarz']],
                        ['code' => 'silver', 'nameTranslations' => ['en' => 'Silver', 'fr' => 'Argent', 'de' => 'Silber']],
                        ['code' => 'blue', 'nameTranslations' => ['en' => 'Blue', 'fr' => 'Bleu', 'de' => 'Blau']],
                    ],
                ],
                'secondary' => [
                    'nameTranslations' => ['en' => 'Storage', 'fr' => 'Stockage', 'de' => 'Speicher'],
                    'values' => [
                        ['code' => '128gb', 'nameTranslations' => ['en' => '128 GB', 'fr' => '128 Go', 'de' => '128 GB']],
                        ['code' => '256gb', 'nameTranslations' => ['en' => '256 GB', 'fr' => '256 Go', 'de' => '256 GB']],
                        ['code' => '512gb', 'nameTranslations' => ['en' => '512 GB', 'fr' => '512 Go', 'de' => '512 GB']],
                    ],
                ],
            ],
            'fashion' => [
                'code' => [
                    'nameTranslations' => ['en' => 'Color', 'fr' => 'Couleur', 'de' => 'Farbe'],
                    'values' => [
                        ['code' => 'black', 'nameTranslations' => ['en' => 'Black', 'fr' => 'Noir', 'de' => 'Schwarz']],
                        ['code' => 'navy', 'nameTranslations' => ['en' => 'Navy', 'fr' => 'Marine', 'de' => 'Navy']],
                        ['code' => 'ivory', 'nameTranslations' => ['en' => 'Ivory', 'fr' => 'Ivoire', 'de' => 'Elfenbein']],
                    ],
                ],
                'secondary' => [
                    'nameTranslations' => ['en' => 'Size', 'fr' => 'Taille', 'de' => 'Groesse'],
                    'values' => [
                        ['code' => 's', 'nameTranslations' => ['en' => 'Small', 'fr' => 'Petit', 'de' => 'Small']],
                        ['code' => 'm', 'nameTranslations' => ['en' => 'Medium', 'fr' => 'Moyen', 'de' => 'Medium']],
                        ['code' => 'l', 'nameTranslations' => ['en' => 'Large', 'fr' => 'Grand', 'de' => 'Large']],
                    ],
                ],
            ],
            default => [
                'code' => [
                    'nameTranslations' => ['en' => 'Finish', 'fr' => 'Finition', 'de' => 'Oberflaeche'],
                    'values' => [
                        ['code' => 'oak', 'nameTranslations' => ['en' => 'Oak', 'fr' => 'Chene', 'de' => 'Eiche']],
                        ['code' => 'walnut', 'nameTranslations' => ['en' => 'Walnut', 'fr' => 'Noyer', 'de' => 'Walnuss']],
                        ['code' => 'stone', 'nameTranslations' => ['en' => 'Stone', 'fr' => 'Pierre', 'de' => 'Stein']],
                    ],
                ],
                'secondary' => [
                    'nameTranslations' => ['en' => 'Size', 'fr' => 'Taille', 'de' => 'Groesse'],
                    'values' => [
                        ['code' => 'small', 'nameTranslations' => ['en' => 'Small', 'fr' => 'Petit', 'de' => 'Klein']],
                        ['code' => 'medium', 'nameTranslations' => ['en' => 'Medium', 'fr' => 'Moyen', 'de' => 'Mittel']],
                        ['code' => 'large', 'nameTranslations' => ['en' => 'Large', 'fr' => 'Grand', 'de' => 'Gross']],
                    ],
                ],
            ],
        };
    }

    /**
     * @return array<string, int>
     */
    public static function minimumCountsPerTenant(): array
    {
        return [
            'catalog_categories' => 18,
            'catalog_products' => 12 * self::PRODUCTS_PER_LEAF,
            'catalog_product_options' => 12 * self::CONFIGURABLE_PRODUCTS_PER_LEAF * 2,
            'catalog_product_option_values' => 12 * self::CONFIGURABLE_PRODUCTS_PER_LEAF * 6,
            'customers' => self::CUSTOMERS_PER_TENANT,
            'orders' => self::ORDERS_PER_TENANT,
            'invoices' => self::INVOICES_PER_TENANT,
            'promotions' => self::PROMOTIONS_PER_TENANT,
            'catalog_configurable_products' => 12 * self::CONFIGURABLE_PRODUCTS_PER_LEAF,
            'catalog_product_variants' => 12 * self::CONFIGURABLE_PRODUCTS_PER_LEAF * self::VARIANTS_PER_CONFIGURABLE,
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function localizedTraits(): array
    {
        return [
            'electronics.en' => ['fast charging', 'all-day battery', 'studio sound', 'precision sensors', 'compact build', 'wide-angle optics'],
            'electronics.fr' => ['charge rapide', 'batterie longue duree', 'son de studio', 'capteurs precis', 'format compact', 'optique grand angle'],
            'electronics.de' => ['Schnellladen', 'lange Akkulaufzeit', 'Studio-Sound', 'praezise Sensoren', 'kompakte Bauweise', 'Weitwinkeloptik'],
            'fashion.en' => ['soft drape', 'breathable fabric', 'structured fit', 'clean finish', 'easy layering', 'day-to-night styling'],
            'fashion.fr' => ['tombe souple', 'matiere respirante', 'coupe structuree', 'finition nette', 'superposition facile', 'style du jour au soir'],
            'fashion.de' => ['weicher Fall', 'atmungsaktiver Stoff', 'strukturierte Passform', 'saubere Verarbeitung', 'leicht zu layern', 'Styling fuer Tag und Abend'],
            'home.en' => ['easy-care finish', 'space-saving footprint', 'warm texture', 'durable hardware', 'modular storage', 'calm silhouette'],
            'home.fr' => ['finition facile a entretenir', 'encombrement reduit', 'texture chaleureuse', 'quincaillerie durable', 'rangement modulaire', 'silhouette apaisante'],
            'home.de' => ['pflegeleichte Oberflaeche', 'platzsparender Fussabdruck', 'warme Textur', 'robuste Beschlaege', 'modularer Stauraum', 'ruhige Silhouette'],
        ];
    }

    /**
     * @return array<string, array{min: int, max: int}>
     */
    public static function priceRanges(): array
    {
        return [
            'electronics' => ['min' => 14900, 'max' => 289900],
            'fashion' => ['min' => 3900, 'max' => 28900],
            'home' => ['min' => 5900, 'max' => 149900],
        ];
    }

    /**
     * @return array<int, array{code: string, name: string, street: string, city: string, state: string, postalCode: string, country: string, priority: int}>
     */
    public static function warehouses(string $tenantAlias): array
    {
        return match ($tenantAlias) {
            'techmart' => [
                ['code' => 'TEC-EAST', 'name' => 'TechMart East Fulfillment', 'street' => '1200 Logistics Way', 'city' => 'Newark', 'state' => 'NJ', 'postalCode' => '07102', 'country' => 'US', 'priority' => 10],
                ['code' => 'TEC-WEST', 'name' => 'TechMart West Fulfillment', 'street' => '880 Signal Park', 'city' => 'Phoenix', 'state' => 'AZ', 'postalCode' => '85004', 'country' => 'US', 'priority' => 20],
            ],
            'fashion_hub' => [
                ['code' => 'FSH-NORTH', 'name' => 'Fashion Hub North Studio', 'street' => '44 Pattern Lane', 'city' => 'Chicago', 'state' => 'IL', 'postalCode' => '60607', 'country' => 'US', 'priority' => 10],
                ['code' => 'FSH-SOUTH', 'name' => 'Fashion Hub South Studio', 'street' => '210 Runway Drive', 'city' => 'Atlanta', 'state' => 'GA', 'postalCode' => '30303', 'country' => 'US', 'priority' => 20],
            ],
            default => [
                ['code' => 'HGP-CNTR', 'name' => 'HomeGoods Central Warehouse', 'street' => '75 Harbor Route', 'city' => 'Columbus', 'state' => 'OH', 'postalCode' => '43215', 'country' => 'US', 'priority' => 10],
                ['code' => 'HGP-SUN', 'name' => 'HomeGoods Sunbelt Warehouse', 'street' => '500 Hearth Avenue', 'city' => 'Dallas', 'state' => 'TX', 'postalCode' => '75201', 'country' => 'US', 'priority' => 20],
            ],
        };
    }
}
